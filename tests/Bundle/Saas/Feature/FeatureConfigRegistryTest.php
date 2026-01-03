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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Enum\FeatureType;
use SolidWorx\Platform\SaasBundle\Exception\UndefinedFeatureException;
use SolidWorx\Platform\SaasBundle\Feature\FeatureConfig;
use SolidWorx\Platform\SaasBundle\Feature\FeatureConfigRegistry;

#[CoversClass(FeatureConfigRegistry::class)]
#[CoversClass(FeatureConfig::class)]
final class FeatureConfigRegistryTest extends TestCase
{
    public function testEmptyRegistry(): void
    {
        $registry = new FeatureConfigRegistry([]);

        self::assertSame([], $registry->all());
        self::assertSame([], $registry->keys());
        self::assertFalse($registry->has('any_feature'));
    }

    public function testRegistryWithFeatures(): void
    {
        $registry = new FeatureConfigRegistry([
            'max_users' => [
                'type' => 'integer',
                'default' => 10,
                'description' => 'Maximum number of users',
            ],
            'api_access' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'API access enabled',
            ],
        ]);

        self::assertTrue($registry->has('max_users'));
        self::assertTrue($registry->has('api_access'));
        self::assertFalse($registry->has('unknown_feature'));

        self::assertSame(['max_users', 'api_access'], $registry->keys());
        self::assertCount(2, $registry->all());
    }

    public function testGetFeatureConfig(): void
    {
        $registry = new FeatureConfigRegistry([
            'max_users' => [
                'type' => 'integer',
                'default' => 10,
                'description' => 'Maximum number of users',
            ],
        ]);

        $config = $registry->get('max_users');

        self::assertSame('max_users', $config->key);
        self::assertSame(FeatureType::INTEGER, $config->type);
        self::assertSame(10, $config->defaultValue);
        self::assertSame('Maximum number of users', $config->description);
    }

    public function testGetUndefinedFeatureThrowsException(): void
    {
        $registry = new FeatureConfigRegistry([]);

        $this->expectException(UndefinedFeatureException::class);
        $this->expectExceptionMessage('Feature "unknown_feature" is not defined');

        $registry->get('unknown_feature');
    }

    public function testFeatureConfigTypes(): void
    {
        $registry = new FeatureConfigRegistry([
            'bool_feature' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'int_feature' => [
                'type' => 'integer',
                'default' => 100,
            ],
            'string_feature' => [
                'type' => 'string',
                'default' => 'dark',
            ],
            'array_feature' => [
                'type' => 'array',
                'default' => ['a', 'b'],
            ],
        ]);

        self::assertSame(FeatureType::BOOLEAN, $registry->get('bool_feature')->type);
        self::assertSame(FeatureType::INTEGER, $registry->get('int_feature')->type);
        self::assertSame(FeatureType::STRING, $registry->get('string_feature')->type);
        self::assertSame(FeatureType::ARRAY, $registry->get('array_feature')->type);
    }

    public function testFeatureConfigToFeatureValue(): void
    {
        $registry = new FeatureConfigRegistry([
            'max_users' => [
                'type' => 'integer',
                'default' => 10,
                'description' => 'Maximum number of users',
            ],
        ]);

        $config = $registry->get('max_users');
        $featureValue = $config->toFeatureValue();

        self::assertSame('max_users', $featureValue->key);
        self::assertSame(FeatureType::INTEGER, $featureValue->type);
        self::assertSame(10, $featureValue->value);
    }

    public function testFeatureWithoutDescription(): void
    {
        $registry = new FeatureConfigRegistry([
            'max_users' => [
                'type' => 'integer',
                'default' => 10,
            ],
        ]);

        $config = $registry->get('max_users');

        self::assertSame('', $config->description);
    }
}
