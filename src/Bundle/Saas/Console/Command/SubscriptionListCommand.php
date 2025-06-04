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

use Carbon\CarbonImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\Common\Util\ClassUtils;
use Override;
use SolidWorx\Platform\PlatformBundle\Console\Command;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepository;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Stringable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Uid\Ulid;
use function array_map;

//
#[AsCommand(name: 'saas:subscription:list', description: 'List all subscriptions')]
final class SubscriptionListCommand extends Command
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to list all subscriptions in the system.')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Filter subscriptions by status', null)
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit number of subscriptions to show', 25)
            ->addOption('latest', 't', InputOption::VALUE_NONE, 'Show only the latest subscriptions')
            ->addOption('ending-soon', 'p', InputOption::VALUE_NONE, 'Show subscriptions ending soon')
        ;
    }

    #[Override]
    protected function handle(): int
    {
        $criteria = new Criteria();
        $expr = Criteria::expr();

        if ($status = $this->io->getOption('status')) {
            $criteria->andWhere($expr->eq('status', SubscriptionStatus::from($status)));
        }

        $limit = (int) $this->io->getOption('limit');
        if ($limit <= 0) {
            $this->io->error('Limit must be a positive integer.');
            return self::FAILURE;
        }

        if ($this->io->getOption('latest') && $this->io->getOption('ending-soon')) {
            $this->io->error('You cannot use both --latest and --ending-soon options together.');
            return self::FAILURE;
        }

        if ($this->io->getOption('latest')) {
            $criteria->andWhere($expr->gte('startDate', CarbonImmutable::now()->subDays(30)->startOfDay()));
            $criteria->orderBy([
                'startDate' => Order::Descending,
            ]);
        }

        if ($this->io->getOption('ending-soon')) {
            $criteria->andWhere($expr->lte('endDate', CarbonImmutable::now()->addDays(7)->endOfDay()));
            $criteria->orderBy([
                'endDate' => Order::Ascending,
            ]);
        }

        $criteria->setMaxResults($limit);

        $subscriptions = $this->subscriptionRepository->matching($criteria);

        if ($subscriptions->count() === 0) {
            $this->io->caution('No subscriptions found.');
            return self::SUCCESS;
        }

        $this->io->title('Subscriptions');
        $this->io->table(
            ['Subscription ID', 'Subscriber', 'Plan', 'Status', 'Start Date', 'End Date'],
            array_map(
                fn ($subscription): array => [
                    $subscription->getSubscriptionId() ?? 'N/A',
                    $this->getSubscriberString($subscription->getSubscriber()),
                    $subscription->getPlan()->getName(),
                    $subscription->getStatus()->value,
                    $subscription->getStartDate()->format('Y-m-d H:i:s'),
                    $subscription->getEndDate()->format('Y-m-d H:i:s'),
                ],
                $subscriptions->toArray()
            )
        );

        return self::SUCCESS;
    }

    private function getSubscriberString(SubscribableInterface $subscriber): string|int|Ulid
    {
        if ($subscriber instanceof Stringable || method_exists($subscriber, '__toString')) {
            return (string) $subscriber;
        }

        if (method_exists($subscriber, 'getId')) {
            return $subscriber->getId() . '@' . ClassUtils::getClass($subscriber);
        }

        return spl_object_hash($subscriber) . '@' . ClassUtils::getClass($subscriber);
    }
}
