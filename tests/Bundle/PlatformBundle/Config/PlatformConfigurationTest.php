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

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfiguration;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ProfileType;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @phpstan-type ProfileConfig array{
 *     form_type: string,
 *     templates: array{show: string, edit: string, change_password: string},
 *     password: array{min_length: int, strength: string, check_compromised: bool},
 * }
 */
#[CoversClass(PlatformConfiguration::class)]
final class PlatformConfigurationTest extends TestCase
{
    private PlatformConfiguration $configuration;

    private Processor $processor;

    #[Override]
    protected function setUp(): void
    {
        $this->configuration = new PlatformConfiguration();
        $this->processor = new Processor();
    }

    public function testGetConfigSectionKeyReturnsEmptyString(): void
    {
        self::assertSame('', $this->configuration->getConfigSectionKey());
    }

    public function testTreeBuilderRootNodeIsNamedPlatform(): void
    {
        $tree = $this->configuration->getTreeBuilder()->buildTree();
        self::assertSame('platform', $tree->getName());
    }

    public function testTreeBuilderRootNodeIsArrayNode(): void
    {
        $tree = $this->configuration->getTreeBuilder()->buildTree();
        self::assertInstanceOf(ArrayNode::class, $tree);
    }

    public function testGetTreeBuilderReturnsFreshInstanceEachCall(): void
    {
        self::assertNotSame(
            $this->configuration->getTreeBuilder(),
            $this->configuration->getTreeBuilder(),
        );
    }

    public function testDefaultNameIsSolidWorxPlatform(): void
    {
        $result = $this->process([]);
        self::assertSame('SolidWorx Platform', $result['name']);
    }

    public function testDefaultVersionIs100(): void
    {
        $result = $this->process([]);
        self::assertSame('1.0.0', $result['version']);
    }

    public function testDefaultTwoFactorEnabledIsFalse(): void
    {
        $result = $this->process([]);
        self::assertFalse($result['security']['two_factor']['enabled']);
    }

    public function testDefaultBaseTemplateIsNull(): void
    {
        $result = $this->process([]);
        self::assertNull($result['security']['two_factor']['base_template']);
    }

    public function testDefaultEnableUtcDateIsTrue(): void
    {
        $result = $this->process([]);
        self::assertTrue($result['doctrine']['types']['enable_utc_date']);
    }

    public function testDefaultUserModelIsUserClass(): void
    {
        $result = $this->process([]);
        self::assertSame(User::class, $result['models']['user']);
    }

    public function testCustomNameIsApplied(): void
    {
        $result = $this->process([
            'name' => 'My App',
        ]);
        self::assertSame('My App', $result['name']);
    }

    public function testCustomVersionIsApplied(): void
    {
        $result = $this->process([
            'version' => '2.5.0',
        ]);
        self::assertSame('2.5.0', $result['version']);
    }

    public function testTwoFactorCanBeEnabled(): void
    {
        $result = $this->process([
            'security' => [
                'two_factor' => [
                    'enabled' => true,
                ],
            ],
        ]);
        self::assertTrue($result['security']['two_factor']['enabled']);
    }

    public function testBaseTemplateCanBeSet(): void
    {
        $result = $this->process([
            'security' => [
                'two_factor' => [
                    'base_template' => '@App/layout/base.html.twig',
                ],
            ],
        ]);
        self::assertSame('@App/layout/base.html.twig', $result['security']['two_factor']['base_template']);
    }

    public function testUtcDateCanBeDisabled(): void
    {
        $result = $this->process([
            'doctrine' => [
                'types' => [
                    'enable_utc_date' => false,
                ],
            ],
        ]);
        self::assertFalse($result['doctrine']['types']['enable_utc_date']);
    }

    public function testCustomUserModelIsApplied(): void
    {
        $result = $this->process([
            'models' => [
                'user' => 'App\\Entity\\User',
            ],
        ]);
        self::assertSame('App\\Entity\\User', $result['models']['user']);
    }

    public function testProfileDefaultsToThePlatformFormTypeAndTemplates(): void
    {
        $result = $this->process([]);

        self::assertSame(ProfileType::class, $result['profile']['form_type']);
        self::assertSame('@SolidWorxPlatform/Profile/show.html.twig', $result['profile']['templates']['show']);
        self::assertSame('@SolidWorxPlatform/Profile/edit.html.twig', $result['profile']['templates']['edit']);
        self::assertSame('@SolidWorxPlatform/Profile/change_password.html.twig', $result['profile']['templates']['change_password']);
    }

    public function testProfilePasswordRulesDefaultToTwelveCharactersMediumStrengthAndABreachCheck(): void
    {
        $result = $this->process([]);

        self::assertSame(12, $result['profile']['password']['min_length']);
        self::assertSame(PasswordStrengthLevel::Medium->value, $result['profile']['password']['strength']);
        self::assertTrue($result['profile']['password']['check_compromised']);
    }

    public function testACustomProfileFormTypeIsApplied(): void
    {
        $result = $this->process([
            'profile' => [
                'form_type' => TextType::class,
            ],
        ]);

        self::assertSame(TextType::class, $result['profile']['form_type']);
    }

    /**
     * The controller passes the configured class straight to `createForm()`, so a class that is
     * not a form type has to be rejected while the container is being built rather than on the
     * first request to the page.
     */
    public function testAProfileFormTypeThatIsNotAFormTypeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            'profile' => [
                'form_type' => User::class,
            ],
        ]);
    }

    public function testAnUnknownPasswordStrengthIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            'profile' => [
                'password' => [
                    'strength' => 'unbreakable',
                ],
            ],
        ]);
    }

    public function testAPasswordMinimumLengthBelowOneIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            'profile' => [
                'password' => [
                    'min_length' => 0,
                ],
            ],
        ]);
    }

    public function testProfilePasswordRulesCanBeTightened(): void
    {
        $result = $this->process([
            'profile' => [
                'password' => [
                    'min_length' => 16,
                    'strength' => PasswordStrengthLevel::VeryStrong->value,
                    'check_compromised' => false,
                ],
            ],
        ]);

        self::assertSame(16, $result['profile']['password']['min_length']);
        self::assertSame('very_strong', $result['profile']['password']['strength']);
        self::assertFalse($result['profile']['password']['check_compromised']);
    }

    public function testUnknownKeysAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([
            'unknown_key' => 'value',
        ]);
    }

    public function testFullConfigIsProcessed(): void
    {
        $result = $this->process([
            'name' => 'Acme',
            'version' => '3.0.0',
            'security' => [
                'two_factor' => [
                    'enabled' => true,
                    'base_template' => '@App/2fa.html.twig',
                ],
            ],
            'doctrine' => [
                'types' => [
                    'enable_utc_date' => false,
                ],
            ],
            'models' => [
                'user' => 'App\\Entity\\Admin',
            ],
        ]);

        self::assertSame('Acme', $result['name']);
        self::assertSame('3.0.0', $result['version']);
        self::assertTrue($result['security']['two_factor']['enabled']);
        self::assertSame('@App/2fa.html.twig', $result['security']['two_factor']['base_template']);
        self::assertFalse($result['doctrine']['types']['enable_utc_date']);
        self::assertSame('App\\Entity\\Admin', $result['models']['user']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{name: string, version: string, security: array{two_factor: array{enabled: bool, base_template: string|null}}, doctrine: array{types: array{enable_utc_date: bool}}, models: array{user: string}, profile: ProfileConfig}
     */
    private function process(array $config): array
    {
        /** @var array{name: string, version: string, security: array{two_factor: array{enabled: bool, base_template: string|null}}, doctrine: array{types: array{enable_utc_date: bool}}, models: array{user: string}, profile: ProfileConfig} */
        return $this->processor->process($this->configuration->getTreeBuilder()->buildTree(), [$config]);
    }
}
