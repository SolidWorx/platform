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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Kernel;

#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['PLATFORM_TEST_FLAG'], $_ENV['PLATFORM_TEST_NAME']);
    }

    public function testEnvValuesAreResolved(): void
    {
        $_ENV['PLATFORM_TEST_FLAG'] = '1';
        $_ENV['PLATFORM_TEST_NAME'] = 'Acme';

        $this->assertSame([
            'name' => 'Acme',
            'security' => [
                'email_verification' => [
                    'enabled' => true,
                ],
                'two_factor' => [
                    'enabled' => false,
                ],
            ],
        ], Kernel::resolveEnvValues([
            'name' => '%env(PLATFORM_TEST_NAME)%',
            'security' => [
                'email_verification' => [
                    'enabled' => '%env(bool:PLATFORM_TEST_FLAG)%',
                ],
                'two_factor' => [
                    'enabled' => false,
                ],
            ],
        ]));
    }

    public function testFalsyBoolEnvValue(): void
    {
        $_ENV['PLATFORM_TEST_FLAG'] = 'false';

        $this->assertFalse(Kernel::resolveEnvValues('%env(bool:PLATFORM_TEST_FLAG)%'));
    }

    public function testSymfonyEnvProcessorsAreSupported(): void
    {
        $_ENV['PLATFORM_TEST_FLAG'] = '42';
        $_ENV['PLATFORM_TEST_NAME'] = '{"a":1}';

        $this->assertSame(42, Kernel::resolveEnvValues('%env(int:PLATFORM_TEST_FLAG)%'));
        $this->assertSame([
            'a' => 1,
        ], Kernel::resolveEnvValues('%env(json:PLATFORM_TEST_NAME)%'));
        $this->assertNull(Kernel::resolveEnvValues('%env(default::PLATFORM_TEST_UNDEFINED)%'));
        $this->assertSame('100%', Kernel::resolveEnvValues('100%'));
    }
}
