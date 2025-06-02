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

use Doctrine\Common\Util\ClassUtils;
use Override;
use SolidWorx\Platform\PlatformBundle\Console\Command;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepository;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Stringable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Uid\Ulid;
use function array_map;

#[AsCommand(name: 'saas:subscription:list', description: 'List all subscriptions')]
final class SubscriptionListCommand extends Command
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository
    ) {
        parent::__construct();
    }

    #[Override]
    protected function handle(): int
    {
        $subscriptions = $this->subscriptionRepository->findAll();

        if ($subscriptions === []) {
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
                $subscriptions
            )
        );

        return self::SUCCESS;
    }

    private function getSubscriberString(SubscribableInterface $subscriber): string|int|Ulid
    {
        if (method_exists($subscriber, '__toString') || $subscriber instanceof Stringable) {
            return (string) $subscriber;
        }

        if (method_exists($subscriber, 'getId')) {
            return $subscriber->getId() . '@' . ClassUtils::getClass($subscriber);
        }

        return spl_object_hash($subscriber) . '@' . ClassUtils::getClass($subscriber);
    }
}
