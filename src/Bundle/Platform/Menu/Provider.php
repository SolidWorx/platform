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

namespace SolidWorx\Platform\PlatformBundle\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Knp\Menu\Provider\MenuProviderInterface;
use Override;
use SplPriorityQueue;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class Provider implements MenuProviderInterface
{
    /**
     * @var array<string, SplPriorityQueue<int, callable>>
     */
    private array $list = [];

    private int $seq = 0;

    public function __construct(
        private readonly FactoryInterface $factory,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Override]
    public function get(string $name, array $options = []): ItemInterface
    {
        $root = $this->factory->createItem('root', $options);

        /*
         * Iterating over a SplPriorityQueue will empty the queue,
         * so we clone it to keep the original intact. This allows
         * rendering the same menu multiple times
         */
        $clone = clone $this->list[$name];
        $clone->setExtractFlags(SplPriorityQueue::EXTR_DATA);

        foreach ($clone as $builder) {
            $builder($root, $options);

            /*
             * Remove items that the user is not authorized to see.
             * This is done after each builder call to ensure that
             * unauthorized items are removed immediately.
             * This then helps for builders that need to check for the existence
             * of certain items before adding their own children.
             */
            $this->removeItems($root);
        }

        return $root;
    }

    #[Override]
    public function has(string $name, array $options = []): bool
    {
        return isset($this->list[$name]);
    }

    public function addBuilder(callable $builder, string $name, int $priority, string $role): void
    {
        // Ensure the menu name is still defined
        // to prevent errors when trying to render a menu
        // that has no authorized items.
        if (! $this->has($name)) {
            $this->list[$name] = new SplPriorityQueue();
        }

        if ($role !== '' && ! $this->authorizationChecker->isGranted($role)) {
            return; // Skip adding the builder if the role is not granted
        }

        /*
         * Use stable ordering for equal priorities (FIFO within the same priority)
         * This is done by adding a sequence number to the priority.
         * The sequence number is incremented with each insertion.
         * This ensures that builders with the same priority are executed in the order they were added.
         */
        $this->list[$name]->insert($builder, [$priority, -$this->seq++]);
    }

    private function removeItems(ItemInterface $item): void
    {
        foreach ($item->getChildren() as $child) {
            $role = $child->getExtra('role');
            if ($role && ! $this->authorizationChecker->isGranted($role)) {
                $item->removeChild($child);
            } else {
                $this->removeItems($child);
            }
        }
    }
}
