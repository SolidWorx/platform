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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfiguration;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ChangePassword;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\EditProfile;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ShowProfile;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\SolidWorxPlatformExtension;
use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ProfileType;
use SolidWorx\Platform\PlatformBundle\Menu\ProfileMenuBuilder;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicy;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicyInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function is_string;
use function sprintf;

/**
 * The profile services are wired from container parameters, and a parameter name is a string on
 * both sides — the extension that sets it and the `#[Autowire]` attribute that reads it. Nothing
 * checks that the two agree until the container is compiled, which these tests do up front.
 */
#[CoversClass(SolidWorxPlatformExtension::class)]
#[UsesClass(PlatformConfiguration::class)]
#[UsesClass(PasswordStrengthLevel::class)]
final class ProfileServicesTest extends TestCase
{
    /**
     * Every class the profile pages are built from, in the order a request touches them.
     *
     * @return iterable<string, array{class-string}>
     */
    public static function profileServices(): iterable
    {
        yield 'menu entry' => [ProfileMenuBuilder::class];
        yield 'profile page' => [ShowProfile::class];
        yield 'edit page' => [EditProfile::class];
        yield 'change password page' => [ChangePassword::class];
        yield 'profile form' => [ProfileType::class];
        yield 'password policy' => [PasswordPolicy::class];
    }

    /**
     * @param class-string $service
     */
    #[DataProvider('profileServices')]
    public function testItIsRegisteredInTheContainer(string $service): void
    {
        self::assertTrue(self::container()->hasDefinition($service));
    }

    /**
     * The profile services that read configuration out of container parameters.
     *
     * @return iterable<string, array{class-string}>
     */
    public static function parameterisedServices(): iterable
    {
        yield 'profile page' => [ShowProfile::class];
        yield 'edit page' => [EditProfile::class];
        yield 'change password page' => [ChangePassword::class];
        yield 'profile form' => [ProfileType::class];
        yield 'password policy' => [PasswordPolicy::class];
    }

    /**
     * @param class-string $service
     */
    #[DataProvider('parameterisedServices')]
    public function testEveryParameterItAutowiresExists(string $service): void
    {
        $container = self::container();

        $parameters = self::autowiredParameters($service);

        self::assertNotSame([], $parameters, sprintf('%s autowires no parameters at all — has the wiring moved?', $service));

        foreach ($parameters as $parameter) {
            self::assertTrue(
                $container->hasParameter($parameter),
                sprintf('%s autowires the parameter "%s", which nothing sets.', $service, $parameter),
            );
        }
    }

    public function testThePasswordPolicyIsResolvableThroughItsContract(): void
    {
        $container = self::container();

        self::assertTrue($container->hasAlias(PasswordPolicyInterface::class));
        self::assertSame(PasswordPolicy::class, (string) $container->getAlias(PasswordPolicyInterface::class));
    }

    public function testTheDefaultParametersMatchTheConfigurationDefaults(): void
    {
        $container = self::container();

        self::assertSame(ProfileType::class, $container->getParameter('solidworx_platform.profile.form_type'));
        self::assertSame('@SolidWorxPlatform/Profile/show.html.twig', $container->getParameter('solidworx_platform.profile.templates.show'));
        self::assertSame('@SolidWorxPlatform/Profile/edit.html.twig', $container->getParameter('solidworx_platform.profile.templates.edit'));
        self::assertSame('@SolidWorxPlatform/Profile/change_password.html.twig', $container->getParameter('solidworx_platform.profile.templates.change_password'));
        self::assertSame(12, $container->getParameter('solidworx_platform.profile.password.min_length'));
        self::assertSame(PasswordStrengthLevel::Medium, $container->getParameter('solidworx_platform.profile.password.strength'));
        self::assertTrue($container->getParameter('solidworx_platform.profile.password.check_compromised'));
    }

    /**
     * The strength is stored as the enum rather than its backing value, so the policy never has
     * to re-parse — and never has to handle — a string the configuration already validated.
     */
    public function testTheConfiguredStrengthIsStoredAsTheEnum(): void
    {
        $container = self::container([
            'profile' => [
                'password' => [
                    'strength' => 'very_strong',
                ],
            ],
        ]);

        self::assertSame(
            PasswordStrengthLevel::VeryStrong,
            $container->getParameter('solidworx_platform.profile.password.strength'),
        );
    }

    public function testTheProfilePagesLearnWhetherTwoFactorIsEnabled(): void
    {
        self::assertFalse(self::container()->getParameter('solidworx_platform.security.two_factor.enabled'));

        self::assertTrue(
            self::container([
                'security' => [
                    'two_factor' => [
                        'enabled' => true,
                    ],
                ],
            ])
                ->getParameter('solidworx_platform.security.two_factor.enabled'),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function container(array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();

        new SolidWorxPlatformExtension($config)->load([], $container);

        return $container;
    }

    /**
     * The parameter names a class pulls in through `#[Autowire(param: …)]` on its constructor.
     *
     * @param class-string $service
     *
     * @return list<string>
     */
    private static function autowiredParameters(string $service): array
    {
        $constructor = new ReflectionClass($service)->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $parameters = [];

        foreach ($constructor->getParameters() as $parameter) {
            foreach ($parameter->getAttributes(Autowire::class) as $attribute) {
                $param = $attribute->newInstance()->value;

                if (is_string($param) && str_starts_with($param, '%') && str_ends_with($param, '%')) {
                    $parameters[] = trim($param, '%');
                }
            }
        }

        return $parameters;
    }
}
