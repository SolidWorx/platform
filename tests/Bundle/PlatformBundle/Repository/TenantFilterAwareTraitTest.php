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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidWorx\Platform\PlatformBundle\Doctrine\Filter\TenantFilter;
use SolidWorx\Platform\PlatformBundle\Repository\TenantFilterAwareTrait;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\TenantOrmTestCase;
use Symfony\Bridge\Doctrine\Types\UlidType;

#[CoversClass(TenantFilterAwareTrait::class)]
final class TenantFilterAwareTraitTest extends TenantOrmTestCase
{
    public function testWithoutTenantFilterSuspendsAndRestores(): void
    {
        $entityManager = $this->createTenantEntityManager();
        $entityManager->getFilters()
            ->enable(TenantFilter::NAME)
            ->setParameter(TenantFilter::PARAMETER, $this->ulid(), UlidType::NAME);

        $repository = new readonly class($entityManager) {
            use TenantFilterAwareTrait;

            public function __construct(private EntityManagerInterface $entityManager)
            {
            }

            /**
             * @template T
             *
             * @param callable(): T $callback
             *
             * @return T
             */
            public function run(callable $callback): mixed
            {
                return $this->withoutTenantFilter($callback);
            }

            protected function getEntityManager(): EntityManagerInterface
            {
                return $this->entityManager;
            }
        };

        $insideEnabled = $repository->run(static fn (): bool => $entityManager->getFilters()->isEnabled(TenantFilter::NAME));

        $this->assertFalse($insideEnabled);
        $this->assertTrue($entityManager->getFilters()->isEnabled(TenantFilter::NAME));
    }
}
