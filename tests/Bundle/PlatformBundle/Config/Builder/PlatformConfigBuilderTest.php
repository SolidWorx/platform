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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Config\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Config\Builder\PlatformConfigBuilder;
use SolidWorx\Platform\PlatformBundle\Config\Builder\ProfileConfigBuilder;
use SolidWorx\Platform\PlatformBundle\Config\Builder\SecurityConfigBuilder;
use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;

#[CoversClass(PlatformConfigBuilder::class)]
#[UsesClass(SecurityConfigBuilder::class)]
#[UsesClass(ProfileConfigBuilder::class)]
#[UsesClass(PasswordStrengthLevel::class)]
final class PlatformConfigBuilderTest extends TestCase
{
    public function testBuildAlwaysWrapsUnderPlatformKey(): void
    {
        $result = PlatformConfigBuilder::create()->build();

        self::assertArrayHasKey('platform', $result);
    }

    public function testDefaultNameAndVersion(): void
    {
        $result = PlatformConfigBuilder::create()->build();

        self::assertSame('SolidWorx Platform', self::section($result, 'platform')['name']);
        self::assertSame('1.0.0', self::section($result, 'platform')['version']);
    }

    public function testNameAndVersionCanBeOverridden(): void
    {
        $result = PlatformConfigBuilder::create()
            ->name('My App')
            ->version('2.5.0')
            ->build();

        self::assertSame('My App', self::section($result, 'platform')['name']);
        self::assertSame('2.5.0', self::section($result, 'platform')['version']);
    }

    public function testUserModelAppearsInBuild(): void
    {
        $result = PlatformConfigBuilder::create()
            ->userModel('App\Entity\User')
            ->build();

        self::assertSame('App\Entity\User', self::section($result, 'platform', 'models')['user']);
    }

    public function testModelsAbsentWhenNotSet(): void
    {
        $result = PlatformConfigBuilder::create()->build();
        self::assertArrayNotHasKey('models', self::section($result, 'platform'));
    }

    public function testEnableUtcDateAppearsInBuild(): void
    {
        $result = PlatformConfigBuilder::create()
            ->enableUtcDate(true)
            ->build();

        self::assertTrue(self::section($result, 'platform', 'doctrine', 'types')['enable_utc_date']);
    }

    public function testDoctrineAbsentWhenUtcDateNotSet(): void
    {
        $result = PlatformConfigBuilder::create()->build();
        self::assertArrayNotHasKey('doctrine', self::section($result, 'platform'));
    }

    public function testWithSaasConfigInjectsUnderSaasKey(): void
    {
        $saas = [
            'doctrine' => [
                'subscriptions' => [
                    'entity' => 'App\Entity\Subscription',
                ],
            ],
        ];
        $result = PlatformConfigBuilder::create()->withSaasConfig($saas)->build();

        self::assertSame($saas, self::section($result, 'platform')['saas']);
    }

    public function testSaasAbsentWhenNotSet(): void
    {
        $result = PlatformConfigBuilder::create()->build();
        self::assertArrayNotHasKey('saas', self::section($result, 'platform'));
    }

    public function testWithUiConfigInjectsUnderUiKey(): void
    {
        $ui = [
            'icon_pack' => 'tabler',
        ];
        $result = PlatformConfigBuilder::create()->withUiConfig($ui)->build();

        self::assertSame($ui, self::section($result, 'platform')['ui']);
    }

    public function testUiAbsentWhenNotSet(): void
    {
        $result = PlatformConfigBuilder::create()->build();
        self::assertArrayNotHasKey('ui', self::section($result, 'platform'));
    }

    public function testSecurityBuilderChainReturnsParent(): void
    {
        $builder = PlatformConfigBuilder::create();
        $securityBuilder = $builder->security();

        self::assertSame($builder, $securityBuilder->end());
    }

    public function testProfileBuilderChainReturnsParent(): void
    {
        $builder = PlatformConfigBuilder::create();
        $profileBuilder = $builder->profile();

        self::assertSame($builder, $profileBuilder->end());
    }

    public function testProfileAbsentWhenNotSet(): void
    {
        $result = PlatformConfigBuilder::create()->build();

        self::assertArrayNotHasKey('profile', self::section($result, 'platform'));
    }

    /**
     * Only what was actually set is emitted, so the configuration tree keeps supplying the
     * defaults for everything else.
     */
    public function testProfileOnlyEmitsWhatWasSet(): void
    {
        $result = PlatformConfigBuilder::create()
            ->profile()
                ->passwordMinLength(16)
            ->end()
            ->build();

        self::assertSame(
            [
                'password' => [
                    'min_length' => 16,
                ],
            ],
            self::section($result, 'platform', 'profile'),
        );
    }

    public function testProfileBuildsTheWholeSection(): void
    {
        $result = PlatformConfigBuilder::create()
            ->profile()
                ->formType(SecurityConfigBuilder::class)
                ->showTemplate('@App/profile/show.html.twig')
                ->editTemplate('@App/profile/edit.html.twig')
                ->changePasswordTemplate('@App/profile/password.html.twig')
                ->passwordMinLength(16)
                ->passwordStrength(PasswordStrengthLevel::Strong)
                ->checkCompromisedPassword(false)
            ->end()
            ->build();

        self::assertSame(
            [
                'form_type' => SecurityConfigBuilder::class,
                'templates' => [
                    'show' => '@App/profile/show.html.twig',
                    'edit' => '@App/profile/edit.html.twig',
                    'change_password' => '@App/profile/password.html.twig',
                ],
                'password' => [
                    'min_length' => 16,
                    'strength' => 'strong',
                    'check_compromised' => false,
                ],
            ],
            self::section($result, 'platform', 'profile'),
        );
    }

    /**
     * Walk a nested key path, asserting each step is an array, and return the sub-array.
     *
     * @param array<array-key, mixed> $result
     * @return array<array-key, mixed>
     */
    private static function section(array $result, string ...$keys): array
    {
        $current = $result;
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $current);
            $value = $current[$key];
            self::assertIsArray($value);
            $current = $value;
        }

        return $current;
    }
}
