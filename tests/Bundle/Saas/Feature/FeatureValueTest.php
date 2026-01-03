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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Enum\FeatureType;
use SolidWorx\Platform\SaasBundle\Feature\FeatureValue;

#[CoversClass(FeatureValue::class)]
final class FeatureValueTest extends TestCase
{
    public function testUnlimitedIntegerFeature(): void
    {
        $feature = new FeatureValue('max_users', FeatureType::INTEGER, FeatureValue::UNLIMITED);

        self::assertTrue($feature->isUnlimited());
        self::assertTrue($feature->isEnabled());
        self::assertTrue($feature->allows(1000000));
        self::assertNull($feature->getRemainingQuota(100));
    }

    public function testLimitedIntegerFeature(): void
    {
        $feature = new FeatureValue('max_users', FeatureType::INTEGER, 10);

        self::assertFalse($feature->isUnlimited());
        self::assertTrue($feature->isEnabled());
        self::assertSame(10, $feature->asInt());
        self::assertTrue($feature->allows(5));
        self::assertTrue($feature->allows(9));
        self::assertFalse($feature->allows(10));
        self::assertFalse($feature->allows(15));
        self::assertSame(5, $feature->getRemainingQuota(5));
        self::assertSame(0, $feature->getRemainingQuota(10));
        self::assertSame(0, $feature->getRemainingQuota(15));
    }

    public function testZeroIntegerFeatureIsDisabled(): void
    {
        $feature = new FeatureValue('max_users', FeatureType::INTEGER, 0);

        self::assertFalse($feature->isUnlimited());
        self::assertFalse($feature->isEnabled());
        self::assertSame(0, $feature->asInt());
    }

    public function testBooleanFeatureEnabled(): void
    {
        $feature = new FeatureValue('api_access', FeatureType::BOOLEAN, true);

        self::assertFalse($feature->isUnlimited());
        self::assertTrue($feature->isEnabled());
        self::assertTrue($feature->asBool());
        self::assertTrue($feature->allows(0));
    }

    public function testBooleanFeatureDisabled(): void
    {
        $feature = new FeatureValue('api_access', FeatureType::BOOLEAN, false);

        self::assertFalse($feature->isUnlimited());
        self::assertFalse($feature->isEnabled());
        self::assertFalse($feature->asBool());
        self::assertFalse($feature->allows(0));
    }

    public function testStringFeature(): void
    {
        $feature = new FeatureValue('theme', FeatureType::STRING, 'dark');

        self::assertFalse($feature->isUnlimited());
        self::assertTrue($feature->isEnabled());
        self::assertSame('dark', $feature->asString());
    }

    public function testEmptyStringFeatureIsDisabled(): void
    {
        $feature = new FeatureValue('theme', FeatureType::STRING, '');

        self::assertFalse($feature->isEnabled());
    }

    public function testArrayFeature(): void
    {
        $feature = new FeatureValue('integrations', FeatureType::ARRAY, ['slack', 'jira', 'github']);

        self::assertFalse($feature->isUnlimited());
        self::assertTrue($feature->isEnabled());
        self::assertSame(['slack', 'jira', 'github'], $feature->asArray());
    }

    public function testEmptyArrayFeatureIsDisabled(): void
    {
        $feature = new FeatureValue('integrations', FeatureType::ARRAY, []);

        self::assertFalse($feature->isEnabled());
    }

    #[DataProvider('typeConversionProvider')]
    public function testTypeConversions(FeatureType $type, int|bool|string $value, int $expectedInt, bool $expectedBool, string $expectedString): void
    {
        $feature = new FeatureValue('test', $type, $value);

        self::assertSame($expectedInt, $feature->asInt());
        self::assertSame($expectedBool, $feature->asBool());
        self::assertSame($expectedString, $feature->asString());
    }

    /**
     * @return iterable<array{FeatureType, int|bool|string, int, bool, string}>
     */
    public static function typeConversionProvider(): iterable
    {
        yield 'integer 10' => [FeatureType::INTEGER, 10, 10, true, '10'];
        yield 'integer 0' => [FeatureType::INTEGER, 0, 0, false, '0'];
        yield 'boolean true' => [FeatureType::BOOLEAN, true, 1, true, 'true'];
        yield 'boolean false' => [FeatureType::BOOLEAN, false, 0, false, 'false'];
        yield 'string value' => [FeatureType::STRING, 'test', 0, true, 'test'];
        yield 'string empty' => [FeatureType::STRING, '', 0, false, ''];
    }

    public function testRemainingQuotaForNonIntegerReturnsNull(): void
    {
        $boolFeature = new FeatureValue('api_access', FeatureType::BOOLEAN, true);
        $stringFeature = new FeatureValue('theme', FeatureType::STRING, 'dark');

        self::assertNull($boolFeature->getRemainingQuota(0));
        self::assertNull($stringFeature->getRemainingQuota(0));
    }
}
