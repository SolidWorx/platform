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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Doctrine\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Doctrine\EventSubscriber\MetadataSubscriber;

#[CoversClass(MetadataSubscriber::class)]
final class MetadataSubscriberTest extends TestCase
{
    public function testLoadClassMetadata(): void
    {
        $class = (new class() {})::class;

        $dbNames = [
            $class => 'custom_table_name',
        ];

        $classMetadataMock = $this->createMock(ClassMetadata::class);
        $classMetadataMock->expects($this->exactly(2))
            ->method('getName')
            ->willReturn($class);

        $classMetadataMock->expects($this->once())
            ->method('setPrimaryTable')
            ->with([
                'name' => 'custom_table_name',
            ]);

        $eventArgsMock = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgsMock->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($classMetadataMock);

        $subscriber = new MetadataSubscriber($dbNames);
        $subscriber->loadClassMetadata($eventArgsMock);
    }

    public function testLoadClassMetadataWithNoMatch(): void
    {
        $dbNames = [
            (new class() {})::class => 'other_table_name',
        ];

        $classMetadataMock = $this->createMock(ClassMetadata::class);
        $classMetadataMock->expects($this->once())
            ->method('getName')
            ->willReturn('Test\Entity\Example');

        $classMetadataMock->expects($this->never())
            ->method('setPrimaryTable');

        $eventArgsMock = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgsMock->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($classMetadataMock);

        $subscriber = new MetadataSubscriber($dbNames);
        $subscriber->loadClassMetadata($eventArgsMock);
    }
}
