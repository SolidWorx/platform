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

namespace SolidWorx\Platform\SaasBundle\Console\Command;

use DateInterval;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use SolidWorx\Platform\PlatformBundle\Console\Command;
use SolidWorx\Platform\SaasBundle\Dto\IntegrationProduct;
use SolidWorx\Platform\SaasBundle\Dto\IntegrationProductPrice;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\PlanPrice;
use SolidWorx\Platform\SaasBundle\Integration\PaymentIntegrationInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * One-shot migration from the legacy "Plan = variant" schema to the new
 * "Plan (product) + PlanPrice (variant)" schema.
 *
 * Workflow:
 *   1. Apply the additive schema change manually: create `saas_plan_price`,
 *      add `plan_price_id` to `saas_subscription`. Keep the legacy
 *      `saas_plan.price` and `saas_subscription.plan_id` columns in place
 *      until this command finishes.
 *   2. Run this command (use --dry-run first).
 *   3. After verification, drop the legacy columns.
 */
#[AsCommand(name: 'saas:migrate-plan-structure', description: 'Backfill Plan/PlanPrice rows from the legacy variant-as-Plan schema')]
final class MigrateSaasPlanStructureCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
        private readonly PaymentIntegrationInterface $paymentIntegration,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the planned changes without writing anything');
    }

    #[Override]
    protected function handle(): int
    {
        $dryRun = (bool) $this->io->getOption('dry-run');

        $variantIndex = $this->buildVariantIndex();
        $totalVariants = array_sum(array_map(static fn (array $entry): int => count($entry['prices']), $variantIndex));
        $this->io->writeln(sprintf('Discovered %d billable variant(s) across %d product(s) from the payment provider.', $totalVariants, count($variantIndex)));

        $legacyPlans = $this->fetchLegacyPlans();
        $this->io->writeln(sprintf('Found %d legacy plan row(s) to migrate.', count($legacyPlans)));

        $this->em->beginTransaction();

        try {
            /** @var array<string, Plan> $newPlanByProductId */
            $newPlanByProductId = [];
            /** @var array<string, PlanPrice> $newPriceByVariantId */
            $newPriceByVariantId = [];
            /** @var array<string, PlanPrice> $oldPlanIdToNewPrice (binary id => PlanPrice) */
            $oldPlanIdToNewPrice = [];

            foreach ($legacyPlans as $legacy) {
                $legacyPlanId = $this->asString($legacy['plan_id']);
                $legacyPrice = (int) $this->asString($legacy['price']);
                $legacyId = $this->asString($legacy['id']);
                $legacyName = $this->asString($legacy['name']);
                $isFree = $legacyPlanId === '0' && $legacyPrice === 0;

                if ($isFree) {
                    $plan = $newPlanByProductId['__free__']
                        ?? $this->createPlanFromLegacy($legacy, productId: 'free');
                    $newPlanByProductId['__free__'] = $plan;

                    $price = $newPriceByVariantId['0']
                        ?? (new PlanPrice())->setVariantId('0')->setPrice(0)->setInterval(null)->setActive(true);
                    $price->setPlan($plan);
                    $plan->addPrice($price);
                    $newPriceByVariantId['0'] = $price;

                    $oldPlanIdToNewPrice[$legacyId] = $price;

                    if (! $dryRun) {
                        $this->em->persist($plan);
                        $this->em->persist($price);
                        $this->em->flush();
                    }

                    $this->moveFeatures($legacyId, $plan);
                    $this->io->writeln(sprintf(' - free plan "%s" → Plan(free) + PlanPrice(variantId=0)', $legacyName));

                    continue;
                }

                $productInfo = $this->resolveProductForVariant($legacyPlanId, $variantIndex);

                if ($productInfo === null) {
                    $this->io->warning(sprintf('Legacy plan "%s" (variantId=%s) has no matching product in the payment provider; skipping.', $legacyName, $legacyPlanId));

                    continue;
                }

                [$productId, $product, $variantPrice] = $productInfo;

                $plan = $newPlanByProductId[$productId]
                    ?? $this->createPlanFromLegacy($legacy, productId: $productId, productName: $product->name, productDescription: $product->description);
                $newPlanByProductId[$productId] = $plan;

                $price = $newPriceByVariantId[$legacyPlanId]
                    ?? (new PlanPrice())
                        ->setVariantId($legacyPlanId)
                        ->setPrice($variantPrice->price)
                        ->setInterval($variantPrice->interval)
                        ->setActive(true);
                $price->setPlan($plan);
                $plan->addPrice($price);
                $newPriceByVariantId[$legacyPlanId] = $price;

                $oldPlanIdToNewPrice[$legacyId] = $price;

                if (! $dryRun) {
                    $this->em->persist($plan);
                    $this->em->persist($price);
                    $this->em->flush();
                }

                $this->moveFeatures($legacyId, $plan);
                $this->io->writeln(sprintf(' - "%s" (variantId=%s) → Plan(productId=%s) + PlanPrice', $legacyName, $legacyPlanId, $productId));
            }

            $this->repointSubscriptions($oldPlanIdToNewPrice, $dryRun);
            $this->deleteLegacyOrphans(array_keys($oldPlanIdToNewPrice), $dryRun);

            if ($dryRun) {
                $this->em->rollback();
                $this->io->note('Dry run — all changes rolled back.');
            } else {
                $this->em->commit();
                $this->io->success('Migration complete.');
            }
        } catch (Throwable $throwable) {
            $this->em->rollback();
            $this->io->error(sprintf('Migration failed: %s', $throwable->getMessage()));

            throw $throwable;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{product: IntegrationProduct, prices: array<string, IntegrationProductPrice>}>
     */
    private function buildVariantIndex(): array
    {
        $index = [];

        foreach ($this->paymentIntegration->getPlans() as $product) {
            $prices = [];
            foreach ($product->prices as $priceDto) {
                $prices[$priceDto->variantId] = $priceDto;
            }

            $index[$product->id] = [
                'product' => $product,
                'prices' => $prices,
            ];
        }

        return $index;
    }

    /**
     * @param array<string, array{product: IntegrationProduct, prices: array<string, IntegrationProductPrice>}> $variantIndex
     *
     * @return array{0: string, 1: IntegrationProduct, 2: IntegrationProductPrice}|null
     */
    private function resolveProductForVariant(string $variantId, array $variantIndex): ?array
    {
        foreach ($variantIndex as $productId => $entry) {
            if (isset($entry['prices'][$variantId])) {
                return [(string) $productId, $entry['product'], $entry['prices'][$variantId]];
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchLegacyPlans(): array
    {
        // Read the legacy schema directly: `price` is no longer mapped on the
        // Plan entity but must still exist on disk while this command runs.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, description, plan_id, price, trial_duration, `default`, active FROM saas_plan'
        );

        return array_values($rows);
    }

    /**
     * @param array<string, mixed> $legacy
     */
    private function createPlanFromLegacy(
        array $legacy,
        string $productId,
        ?string $productName = null,
        ?string $productDescription = null,
    ): Plan {
        $plan = new Plan();
        $plan->setName($productName ?? $this->asString($legacy['name']));
        $plan->setDescription($productDescription ?? $this->asString($legacy['description'] ?? ''));
        $plan->setPlanId($productId);
        $plan->setActive((bool) $legacy['active']);
        $plan->setDefault((bool) $legacy['default']);

        $trialDuration = $legacy['trial_duration'] ?? null;
        if (is_string($trialDuration) && $trialDuration !== '') {
            $plan->setTrialDuration(new DateInterval($trialDuration));
        }

        return $plan;
    }

    private function moveFeatures(string $legacyPlanId, Plan $newPlan): void
    {
        // Re-parent existing PlanFeature rows from the legacy variant-row Plan
        // onto the new product-row Plan. Deduplicate by featureKey so a plan
        // doesn't end up with two copies of the same feature when both
        // monthly and yearly variant-rows carried the same feature set.
        $existingKeys = [];
        $existingFeatures = $this->connection->fetchAllAssociative(
            'SELECT feature_key FROM saas_plan_feature WHERE plan_id = :plan',
            [
                'plan' => $newPlan->getId()->toBinary(),
            ]
        );
        foreach ($existingFeatures as $row) {
            $existingKeys[$this->asString($row['feature_key'])] = true;
        }

        $features = $this->connection->fetchAllAssociative(
            'SELECT id, feature_key FROM saas_plan_feature WHERE plan_id = :plan',
            [
                'plan' => $legacyPlanId,
            ]
        );

        foreach ($features as $featureRow) {
            $featureKey = $this->asString($featureRow['feature_key']);
            $featureId = $this->asString($featureRow['id']);

            if (isset($existingKeys[$featureKey])) {
                $this->connection->executeStatement(
                    'DELETE FROM saas_plan_feature WHERE id = :id',
                    [
                        'id' => $featureId,
                    ]
                );

                continue;
            }

            $this->connection->executeStatement(
                'UPDATE saas_plan_feature SET plan_id = :new WHERE id = :id',
                [
                    'new' => $newPlan->getId()->toBinary(),
                    'id' => $featureId,
                ]
            );
            $existingKeys[$featureKey] = true;
        }
    }

    /**
     * @param array<string, PlanPrice> $oldPlanIdToNewPrice
     */
    private function repointSubscriptions(array $oldPlanIdToNewPrice, bool $dryRun): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, plan_id FROM saas_subscription'
        );

        $repointed = 0;

        foreach ($rows as $row) {
            $rowPlanId = $this->asString($row['plan_id']);
            $rowId = $this->asString($row['id']);
            $price = $oldPlanIdToNewPrice[$rowPlanId] ?? null;

            if (! $price instanceof PlanPrice) {
                $this->io->warning(sprintf('Subscription %s references unknown plan_id; leaving plan_price_id unset.', bin2hex($rowId)));

                continue;
            }

            if (! $dryRun) {
                $this->connection->executeStatement(
                    'UPDATE saas_subscription SET plan_price_id = :price WHERE id = :id',
                    [
                        'price' => $price->getId()->toBinary(),
                        'id' => $rowId,
                    ]
                );
            }

            ++$repointed;
        }

        $this->io->writeln(sprintf('Re-pointed %d subscription row(s) to plan_price_id.', $repointed));
    }

    /**
     * @param list<string> $migratedLegacyPlanIds
     */
    private function deleteLegacyOrphans(array $migratedLegacyPlanIds, bool $dryRun): void
    {
        if ($migratedLegacyPlanIds === []) {
            return;
        }

        if ($dryRun) {
            $this->io->writeln(sprintf('Would delete %d legacy variant-row plan(s).', count($migratedLegacyPlanIds)));

            return;
        }

        $deleted = $this->connection->executeStatement(
            'DELETE FROM saas_plan WHERE id IN (:ids)',
            [
                'ids' => $migratedLegacyPlanIds,
            ],
            [
                'ids' => ArrayParameterType::STRING,
            ]
        );

        $this->io->writeln(sprintf('Deleted %d legacy variant-row plan(s).', $deleted));
    }

    private function asString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }
}
