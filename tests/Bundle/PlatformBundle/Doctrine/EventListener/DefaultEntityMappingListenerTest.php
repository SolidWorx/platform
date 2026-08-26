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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Doctrine\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Doctrine\EventListener\DefaultEntityMappingListener;
use SolidWorx\Platform\PlatformBundle\Entity\Tenant;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use stdClass;

#[CoversClass(DefaultEntityMappingListener::class)]
final class DefaultEntityMappingListenerTest extends TestCase
{
    public function testSuppressesDefaultEntityWhenOverridden(): void
    {
        $metadata = $this->metadataFor(Tenant::class);

        // A different configured tenant class -> the default should become a mapped superclass.
        $this->listener(stdClass::class, stdClass::class, stdClass::class)->loadClassMetadata(
            $this->event($metadata),
        );

        $this->assertTrue($metadata->isMappedSuperclass);
    }

    public function testSuppressesDefaultUserEntityWhenOverridden(): void
    {
        $metadata = $this->metadataFor(User::class);

        // A different configured user class -> the default should become a mapped superclass.
        $this->listener(stdClass::class, stdClass::class, stdClass::class)->loadClassMetadata(
            $this->event($metadata),
        );

        $this->assertTrue($metadata->isMappedSuperclass);
    }

    public function testKeepsDefaultUserEntityWhenNotOverridden(): void
    {
        $metadata = $this->metadataFor(User::class);

        $this->listener(stdClass::class, stdClass::class, User::class)->loadClassMetadata(
            $this->event($metadata),
        );

        $this->assertFalse($metadata->isMappedSuperclass);
    }

    public function testKeepsDefaultEntityWhenNotOverridden(): void
    {
        $metadata = $this->metadataFor(Tenant::class);

        $this->listener(Tenant::class, stdClass::class, stdClass::class)->loadClassMetadata(
            $this->event($metadata),
        );

        $this->assertFalse($metadata->isMappedSuperclass);
    }

    /**
     * @param class-string $tenantClass
     * @param class-string $userTenantClass
     * @param class-string $userClass
     */
    private function listener(string $tenantClass, string $userTenantClass, string $userClass): DefaultEntityMappingListener
    {
        return new DefaultEntityMappingListener($tenantClass, $userTenantClass, $userClass);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function event(ClassMetadata $metadata): LoadClassMetadataEventArgs
    {
        $event = self::createStub(LoadClassMetadataEventArgs::class);
        $event->method('getClassMetadata')->willReturn($metadata);

        return $event;
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private function metadataFor(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());

        return $metadata;
    }
}
