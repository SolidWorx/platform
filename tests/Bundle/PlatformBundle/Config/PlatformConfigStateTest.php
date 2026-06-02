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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigState;

#[CoversClass(PlatformConfigState::class)]
final class PlatformConfigStateTest extends TestCase
{
    protected function setUp(): void
    {
        PlatformConfigState::clear();
    }

    protected function tearDown(): void
    {
        PlatformConfigState::clear();
    }

    public function testGetReturnsNullAndTwoFactorDisabledWhenNothingPublished(): void
    {
        self::assertNull(PlatformConfigState::get());
        self::assertFalse(PlatformConfigState::isTwoFactorEnabled());
    }

    public function testSetAndGetRoundTrip(): void
    {
        $config = [
            'name' => 'Acme',
            'security' => [
                'two_factor' => [
                    'enabled' => true,
                ],
            ],
        ];

        PlatformConfigState::set($config);

        self::assertSame($config, PlatformConfigState::get());
    }

    public function testIsTwoFactorEnabledIsTrueWhenFlagEnabled(): void
    {
        PlatformConfigState::set([
            'security' => [
                'two_factor' => [
                    'enabled' => true,
                ],
            ],
        ]);

        self::assertTrue(PlatformConfigState::isTwoFactorEnabled());
    }

    public function testIsTwoFactorEnabledIsFalseWhenFlagDisabled(): void
    {
        PlatformConfigState::set([
            'security' => [
                'two_factor' => [
                    'enabled' => false,
                ],
            ],
        ]);

        self::assertFalse(PlatformConfigState::isTwoFactorEnabled());
    }

    public function testIsTwoFactorEnabledIsFalseWhenSectionMissing(): void
    {
        PlatformConfigState::set([
            'name' => 'Acme',
        ]);

        self::assertFalse(PlatformConfigState::isTwoFactorEnabled());
    }

    public function testClearResetsState(): void
    {
        PlatformConfigState::set([
            'security' => [
                'two_factor' => [
                    'enabled' => true,
                ],
            ],
        ]);

        PlatformConfigState::clear();

        self::assertNull(PlatformConfigState::get());
        self::assertFalse(PlatformConfigState::isTwoFactorEnabled());
    }
}
