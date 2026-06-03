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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Doctrine\Filter;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidWorx\Platform\PlatformBundle\Doctrine\Filter\TenantFilter;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Entity\TenantAwareItem;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\TenantOrmTestCase;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantFilter::class)]
final class TenantFilterTest extends TenantOrmTestCase
{
    private EntityManagerInterface $entityManager;

    private Ulid $tenantA;

    private Ulid $tenantB;

    protected function setUp(): void
    {
        $this->entityManager = $this->createTenantEntityManager();

        $this->tenantA = $this->ulid();
        $this->tenantB = $this->ulid();

        $this->persistItem('A-one', $this->tenantA);
        $this->persistItem('A-two', $this->tenantA);
        $this->persistItem('B-one', $this->tenantB);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testFindByReturnsOnlyInScopeRows(): void
    {
        $this->enableFilterFor($this->tenantA);

        $items = $this->entityManager->getRepository(TenantAwareItem::class)->findAll();

        $this->assertCount(2, $items);
    }

    public function testFindOneByIsScoped(): void
    {
        $this->enableFilterFor($this->tenantB);

        $repository = $this->entityManager->getRepository(TenantAwareItem::class);

        $this->assertInstanceOf(TenantAwareItem::class, $repository->findOneBy(['name' => 'B-one']));
        $this->assertNotInstanceOf(TenantAwareItem::class, $repository->findOneBy(['name' => 'A-one']));
    }

    public function testQueryBuilderIsScoped(): void
    {
        $this->enableFilterFor($this->tenantB);

        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(TenantAwareItem::class, 'i')
            ->getQuery()
            ->getSingleScalarResult();

        $this->assertSame(1, (int) $count);
    }

    public function testNoTenantInScopeReturnsAllRows(): void
    {
        $items = $this->entityManager->getRepository(TenantAwareItem::class)->findAll();

        $this->assertCount(3, $items);
    }

    public function testSuspendingFilterBypassesScope(): void
    {
        $filters = $this->entityManager->getFilters();
        $this->enableFilterFor($this->tenantA);

        $filters->suspend(TenantFilter::NAME);
        $all = $this->entityManager->getRepository(TenantAwareItem::class)->findAll();
        $filters->restore(TenantFilter::NAME);

        $scoped = $this->entityManager->getRepository(TenantAwareItem::class)->findAll();

        $this->assertCount(3, $all);
        $this->assertCount(2, $scoped);
    }

    private function persistItem(string $name, Ulid $tenantId): void
    {
        $item = new TenantAwareItem($name, $this->ulid());
        $item->setTenantId($tenantId);

        $this->entityManager->persist($item);
    }

    private function enableFilterFor(Ulid $tenantId): void
    {
        $this->entityManager->getFilters()
            ->enable(TenantFilter::NAME)
            ->setParameter(TenantFilter::PARAMETER, $tenantId, UlidType::NAME);
    }
}
