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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Tenant;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidWorx\Platform\PlatformBundle\Doctrine\Filter\TenantFilter;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\TenantOrmTestCase;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantManager::class)]
final class TenantManagerTest extends TenantOrmTestCase
{
    private EntityManagerInterface $entityManager;

    private TenantContext $context;

    private TenantManager $manager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createTenantEntityManager();
        $this->context = new TenantContext(new EventDispatcher());
        $this->manager = new TenantManager($this->context, $this->entityManager);
    }

    public function testEnableAndDisableFilter(): void
    {
        $this->context->setTenant($this->ulid());

        $this->manager->enableFilter();
        $this->assertTrue($this->manager->isFilterEnabled());

        $this->manager->disableFilter();
        $this->assertFalse($this->manager->isFilterEnabled());
    }

    public function testEnableFilterIsNoopWithoutTenant(): void
    {
        $this->manager->enableFilter();

        $this->assertFalse($this->manager->isFilterEnabled());
    }

    public function testRunWithoutFilterSuspendsAndRestores(): void
    {
        $this->entityManager->getFilters()
            ->enable(TenantFilter::NAME)
            ->setParameter(TenantFilter::PARAMETER, $this->ulid(), UlidType::NAME);

        $insideEnabled = true;

        $result = $this->manager->runWithoutFilter(function () use (&$insideEnabled): string {
            $insideEnabled = $this->manager->isFilterEnabled();

            return 'done';
        });

        $this->assertFalse($insideEnabled);
        $this->assertTrue($this->manager->isFilterEnabled());
        $this->assertSame('done', $result);
    }

    public function testRunAsSwitchesAndRestores(): void
    {
        $first = $this->ulid();
        $second = $this->ulid();
        $this->context->setTenant($first);

        $inside = $this->manager->runAs($second, fn (): ?Ulid => $this->context->getTenantId());

        $this->assertSame($second->toRfc4122(), $inside?->toRfc4122());
        $this->assertSame($first->toRfc4122(), $this->context->getTenantId()?->toRfc4122());
    }
}
