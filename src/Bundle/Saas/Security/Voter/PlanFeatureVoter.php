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

namespace SolidWorx\Platform\SaasBundle\Security\Voter;

use Override;
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureManager;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use function is_array;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Voter for checking plan feature access.
 *
 * Supports two usage patterns:
 *
 * 1. Check if subscriber has feature:
 *    $this->denyAccessUnlessGranted('FEATURE_API_ACCESS', $subscriber);
 *
 * 2. Check if subscriber can use feature with usage limit:
 *    $this->denyAccessUnlessGranted('FEATURE_MAX_USERS', ['subscriber' => $subscriber, 'usage' => 5]);
 *
 * @extends Voter<string, SubscribableInterface|array{subscriber: SubscribableInterface, usage?: int}>
 */
final class PlanFeatureVoter extends Voter
{
    private const string ATTRIBUTE_PREFIX = 'FEATURE_';

    public function __construct(
        private readonly PlanFeatureManager $planFeatureManager,
    ) {
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        if (! str_starts_with($attribute, self::ATTRIBUTE_PREFIX)) {
            return false;
        }

        if ($subject instanceof SubscribableInterface) {
            return true;
        }

        return is_array($subject) && isset($subject['subscriber']) && $subject['subscriber'] instanceof SubscribableInterface;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $featureKey = $this->extractFeatureKey($attribute);

        if ($subject instanceof SubscribableInterface) {
            return $this->planFeatureManager->hasFeatureForSubscriber($subject, $featureKey);
        }

        $subscriber = $subject['subscriber'];
        $usage = $subject['usage'] ?? 0;
        return $this->planFeatureManager->canUseForSubscriber($subscriber, $featureKey, $usage);
    }

    private function extractFeatureKey(string $attribute): string
    {
        return strtolower(substr($attribute, strlen(self::ATTRIBUTE_PREFIX)));
    }
}
