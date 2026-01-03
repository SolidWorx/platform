<?php

declare(strict_types=1);

/*
 * This file is part of SolidWorx Platform project.
 *
 * (c) Pierre du Plessis <open-source@solidworx.co>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SolidWorx\Platform\SaasBundle\Feature;

use InvalidArgumentException;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\PlanFeature;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\FeatureType;
use SolidWorx\Platform\SaasBundle\Exception\UndefinedFeatureException;
use SolidWorx\Platform\SaasBundle\Repository\PlanFeatureRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use SolidWorx\Platform\SaasBundle\Subscription\SubscriptionProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;
use function get_debug_type;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function sprintf;

readonly class PlanFeatureManager implements ResetInterface
{
    public function __construct(
        private FeatureConfigRegistry $configRegistry,
        private PlanFeatureRepositoryInterface $planFeatureRepository,
        private SubscriptionProviderInterface $subscriptionProvider,
        private CacheInterface $cache,
    ) {
    }

    /**
     * Get a feature value for a plan, with config defaults as fallback.
     *
     * @throws UndefinedFeatureException If the feature is not defined in config
     */
    public function getFeature(Plan $plan, string $featureKey): FeatureValue
    {
        $cacheKey = sprintf('feature_%s_%s', $plan->getId()->toBase58(), $featureKey);

        try {
            return $this->cache->get($cacheKey, function () use ($plan, $featureKey) {
                if (! $this->configRegistry->has($featureKey)) {
                    throw new UndefinedFeatureException($featureKey);
                }

                $planFeature = $this->planFeatureRepository->findOneByPlanAndKey($plan, $featureKey);

                if ($planFeature instanceof PlanFeature) {
                    $featureValue = $planFeature->toFeatureValue();
                } else {
                    $featureValue = $this->configRegistry->get($featureKey)->toFeatureValue();
                }

                return $featureValue;
            });
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new UndefinedFeatureException($featureKey, previous: $e);
        }
    }

    /**
     * Check if a plan has a specific feature enabled.
     */
    public function hasFeature(Plan $plan, string $featureKey): bool
    {
        try {
            return $this->getFeature($plan, $featureKey)->isEnabled();
        } catch (UndefinedFeatureException) {
            return false;
        }
    }

    /**
     * Check if a plan allows usage of a feature at the current usage level.
     */
    public function canUse(Plan $plan, string $featureKey, int $currentUsage = 0): bool
    {
        try {
            return $this->getFeature($plan, $featureKey)->allows($currentUsage);
        } catch (UndefinedFeatureException) {
            return false;
        }
    }

    /**
     * Get all features for a plan with their resolved values.
     *
     * @return array<string, FeatureValue>
     */
    public function getAllFeatures(Plan $plan): array
    {
        $features = [];

        foreach ($this->configRegistry->keys() as $key) {
            $features[$key] = $this->getFeature($plan, $key);
        }

        return $features;
    }

    /**
     * Get a feature value for a subscriber (uses their current subscription's plan).
     *
     * @throws UndefinedFeatureException If the feature is not defined
     */
    public function getFeatureForSubscriber(SubscribableInterface $subscriber, string $featureKey): FeatureValue
    {
        $subscription = $this->subscriptionProvider->getSubscriptionFor($subscriber);

        if (! $subscription instanceof Subscription) {
            return $this->configRegistry->get($featureKey)->toFeatureValue();
        }

        return $this->getFeature($subscription->getPlan(), $featureKey);
    }

    /**
     * Check if a subscriber has a specific feature enabled.
     */
    public function hasFeatureForSubscriber(SubscribableInterface $subscriber, string $featureKey): bool
    {
        try {
            return $this->getFeatureForSubscriber($subscriber, $featureKey)->isEnabled();
        } catch (UndefinedFeatureException) {
            return false;
        }
    }

    /**
     * Check if a subscriber can use a feature at the current usage level.
     */
    public function canUseForSubscriber(SubscribableInterface $subscriber, string $featureKey, int $currentUsage = 0): bool
    {
        try {
            return $this->getFeatureForSubscriber($subscriber, $featureKey)->allows($currentUsage);
        } catch (UndefinedFeatureException) {
            return false;
        }
    }

    /**
     * Set a feature override for a plan.
     *
     * @param int|bool|string|array<mixed> $value
     */
    public function setFeature(Plan $plan, string $featureKey, int|bool|string|array $value): void
    {
        $config = $this->configRegistry->get($featureKey);

        $this->validateValueType($config->type, $value);

        $planFeature = $this->planFeatureRepository->findOneByPlanAndKey($plan, $featureKey);

        if (! $planFeature instanceof PlanFeature) {
            $planFeature = new PlanFeature();
            $planFeature->setPlan($plan);
            $planFeature->setFeatureKey($featureKey);
            $planFeature->setType($config->type);
        }

        $planFeature->setValue($value);
        $planFeature->setDescription($config->description);

        $this->planFeatureRepository->save($planFeature);
        $this->invalidateCache($plan);
    }

    /**
     * Remove a feature override for a plan (revert to config default).
     */
    public function removeFeature(Plan $plan, string $featureKey): void
    {
        $planFeature = $this->planFeatureRepository->findOneByPlanAndKey($plan, $featureKey);

        if ($planFeature instanceof PlanFeature) {
            $this->planFeatureRepository->remove($planFeature);
            $this->invalidateCache($plan);
        }
    }

    /**
     * Check if a feature is available on any plan (for upgrade prompts).
     */
    public function isFeatureAvailableOnAnyPlan(string $featureKey): bool
    {
        if (! $this->configRegistry->has($featureKey)) {
            return false;
        }

        $config = $this->configRegistry->get($featureKey);

        if ($config->toFeatureValue()->isEnabled()) {
            return true;
        }

        $planFeatures = $this->planFeatureRepository->findByFeatureKey($featureKey);

        foreach ($planFeatures as $planFeature) {
            if ($planFeature->toFeatureValue()->isEnabled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find all plans that have a specific feature enabled.
     *
     * @return array<Plan>
     */
    public function findPlansWithFeature(string $featureKey, ?Plan $excludePlan = null): array
    {
        $plans = [];
        $planFeatures = $this->planFeatureRepository->findByFeatureKey($featureKey);

        foreach ($planFeatures as $planFeature) {
            $plan = $planFeature->getPlan();

            if ($excludePlan instanceof Plan && $plan->getId()->equals($excludePlan->getId())) {
                continue;
            }

            if ($planFeature->toFeatureValue()->isEnabled()) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }

    /**
     * Get all available feature configurations.
     *
     * @return array<string, FeatureConfig>
     */
    public function getAvailableFeatures(): array
    {
        return $this->configRegistry->all();
    }

    /**
     * Clear the in-memory cache.
     */
    public function reset(): void
    {
        $this->cache->clear();
    }

    private function invalidateCache(Plan $plan): void
    {
        try {
            $this->cache->delete(sprintf('feature_%s_', $plan->getId()->toBase58()) . '*');
        } catch (\Psr\Cache\InvalidArgumentException) {
        }
    }

    private function validateValueType(FeatureType $type, mixed $value): void
    {
        $valid = match ($type) {
            FeatureType::BOOLEAN => is_bool($value),
            FeatureType::INTEGER => is_int($value),
            FeatureType::STRING => is_string($value),
            FeatureType::ARRAY => is_array($value),
        };

        if (! $valid) {
            throw new InvalidArgumentException(sprintf(
                'Feature value must be of type %s, %s given.',
                $type->value,
                get_debug_type($value)
            ));
        }
    }
}
