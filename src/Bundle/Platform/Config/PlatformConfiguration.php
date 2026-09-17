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

namespace SolidWorx\Platform\PlatformBundle\Config;

use Override;
use SolidWorx\Platform\PlatformBundle\Entity\Tenant;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Entity\UserTenant;
use SolidWorx\Platform\PlatformBundle\Form\Type\Tenant\TenantOnboardingType;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserTenantInterface;
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantCreationVoter;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Form\FormTypeInterface;
use function implode;
use function in_array;
use function is_string;
use function is_subclass_of;
use function sprintf;

final class PlatformConfiguration implements PlatformConfigurationInterface
{
    /**
     * The attributes the platform decides with a strategy of its own, always merged over whatever
     * the application configures: dropping one would quietly stop a refusal from counting.
     *
     * @var array<string, string>
     */
    public const array PLATFORM_ACCESS_DECISION_STRATEGIES = [
        TenantCreationVoter::TENANT_CREATE => 'unanimous',
    ];

    /**
     * The strategies Symfony ships, as named in `security.access_decision_manager.strategy`.
     *
     * @var list<string>
     */
    private const array ACCESS_DECISION_STRATEGIES = ['affirmative', 'consensus', 'unanimous', 'priority'];

    #[Override]
    public function getConfigSectionKey(): string
    {
        return '';
    }

    #[Override]
    public function getTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('platform');
        $root = $treeBuilder->getRootNode();

        // @formatter:off
        $root
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('name')
                    ->defaultValue('SolidWorx Platform')
                    ->info('The name of the platform.')
                ->end()
                ->scalarNode('version')
                    ->defaultValue('1.0.0')
                    ->info('The version of the platform.')
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('access_decision')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('strategies')
                                    ->info('Security attributes decided with their own strategy, instead of the one Symfony uses for everything else. Keyed by attribute; one of: ' . implode(', ', self::ACCESS_DECISION_STRATEGIES))
                                    ->useAttributeAsKey('attribute')
                                    ->defaultValue([
                                        TenantCreationVoter::TENANT_CREATE => 'unanimous',
                                    ])
                                    ->scalarPrototype()
                                        ->validate()
                                            ->ifTrue(static fn ($v): bool => ! in_array($v, self::ACCESS_DECISION_STRATEGIES, strict: true))
                                            ->thenInvalid(sprintf('The access decision strategy must be one of %s, got %%s', implode(', ', self::ACCESS_DECISION_STRATEGIES)))
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('two_factor')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultFalse()
                                    ->info('Enable two-factor authentication.')
                                ->end()
                                ->scalarNode('base_template')
                                    ->defaultNull()
                                    ->info('The base layout template for 2FA pages.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('doctrine')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('types')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enable_utc_date')
                                    ->defaultTrue()
                                    ->info('Enable UTC date type.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('models')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('user')
                            ->defaultValue(User::class)
                            ->info('The User model class.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('build')
                    ->addDefaultsIfNotSet()
                    ->info('Static single-executable binary build (platform:build).')
                    ->children()
                        ->scalarNode('name')
                            ->defaultNull()
                            ->info('Display name of the built application. Defaults to platform.name.')
                        ->end()
                        ->scalarNode('description')
                            ->defaultValue('')
                            ->info('One-line description shown by the binary on startup.')
                        ->end()
                        ->scalarNode('binary_name')
                            ->defaultNull()
                            ->info('File name of the produced binary. Defaults to a slug of the build name.')
                        ->end()
                        ->scalarNode('env_prefix')
                            ->defaultNull()
                            ->info("Prefix for the binary's environment variables (e.g. ACME gives ACME_PORT). Defaults to the upper-cased binary name.")
                        ->end()
                        ->scalarNode('default_port')
                            ->defaultValue('8080')
                            ->info('Port the binary listens on when none is given.')
                        ->end()
                        ->scalarNode('output_dir')
                            ->defaultValue('%kernel.project_dir%/build')
                            ->info('Directory the finished binary is written to.')
                        ->end()
                        ->scalarNode('work_dir')
                            ->defaultValue('%kernel.project_dir%/var/build')
                            ->info('Persistent build cache. Keeping it between builds turns an hour into minutes.')
                        ->end()
                        ->arrayNode('php')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('version')
                                    ->defaultNull()
                                    ->info('PHP version to compile. Null lets the build script resolve the latest.')
                                ->end()
                                ->arrayNode('extensions')
                                    ->info('Explicit extension list. Empty derives the set from composer.json.')
                                    ->scalarPrototype()->end()
                                ->end()
                                ->arrayNode('add')
                                    ->info('Extensions appended to the resolved set. Setting this switches the base off composer.json — see the build documentation.')
                                    ->scalarPrototype()->end()
                                ->end()
                                ->arrayNode('remove')
                                    ->info('Extensions subtracted from the resolved set. Setting this switches the base off composer.json — see the build documentation.')
                                    ->scalarPrototype()->end()
                                ->end()
                                ->arrayNode('extension_libs')
                                    ->defaultValue(['libavif', 'nghttp2', 'nghttp3', 'ngtcp2'])
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('exclude')
                            ->info('Paths kept out of the embedded application archive, relative to the project root. Replaces the defaults rather than adding to them; .git, var/cache, var/log and node_modules are always excluded regardless of this setting.')
                            ->defaultValue(['node_modules/', 'var/cache/', 'var/log/', '.git/', 'tests/'])
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('hooks')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('install_check')
                                    ->defaultNull()
                                    ->info('Console command that exits 0 when the app is installed. Workers wait for it before consuming.')
                                ->end()
                                ->arrayNode('on_boot')
                                    ->info('Console commands run each time the binary starts.')
                                    ->defaultValue(['cache:clear'])
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('multi_tenancy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Enable multi-tenancy (data isolation per tenant).')
                        ->end()
                        ->scalarNode('session_key')
                            ->defaultValue('_tenant_id')
                            ->info('The session key holding the selected tenant id.')
                        ->end()
                        ->scalarNode('route_param')
                            ->defaultValue('tenant')
                            ->info('The route parameter holding the tenant id (route resolver).')
                        ->end()
                        ->booleanNode('validate_user_access')
                            ->defaultTrue()
                            ->info('Deny entering a tenant the authenticated user is not a member of.')
                        ->end()
                        ->booleanNode('require_tenant')
                            ->defaultTrue()
                            ->info('Require an authenticated user to always have a tenant in scope, redirecting to selection or onboarding otherwise.')
                        ->end()
                        ->scalarNode('default_route')
                            ->defaultNull()
                            ->info('Route to land on after selecting or creating a tenant, when no page was interrupted. Defaults to "/".')
                        ->end()
                        ->arrayNode('onboarding')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultTrue()
                                    ->info('Let users create workspaces, both their first and any after it. Disable for invite-only apps, where tenants are provisioned out of band.')
                                ->end()
                                ->scalarNode('form_type')
                                    ->defaultValue(TenantOnboardingType::class)
                                    ->info(sprintf('The form type used on the onboarding page. Must implement %s', FormTypeInterface::class))
                                    ->validate()
                                        ->ifTrue(static fn ($v): bool => ! is_string($v) || ! is_subclass_of($v, FormTypeInterface::class))
                                        ->thenInvalid(sprintf('The onboarding form type must implement %s', FormTypeInterface::class))
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('models')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('tenant')
                                    ->defaultValue(Tenant::class)
                                    ->info(sprintf('The Tenant entity class. Must implement %s', TenantInterface::class))
                                    ->validate()
                                        ->ifTrue(static fn ($v): bool => ! is_string($v) || ! is_subclass_of($v, TenantInterface::class))
                                        ->thenInvalid(sprintf('The tenant entity must implement %s', TenantInterface::class))
                                    ->end()
                                ->end()
                                ->scalarNode('user_tenant')
                                    ->defaultValue(UserTenant::class)
                                    ->info(sprintf('The UserTenant (membership) entity class. Must implement %s', UserTenantInterface::class))
                                    ->validate()
                                        ->ifTrue(static fn ($v): bool => ! is_string($v) || ! is_subclass_of($v, UserTenantInterface::class))
                                        ->thenInvalid(sprintf('The user tenant entity must implement %s', UserTenantInterface::class))
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('resolvers')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('domain')
                                    ->defaultTrue()
                                    ->info('Resolve the tenant from a custom request host.')
                                ->end()
                                ->booleanNode('session')
                                    ->defaultTrue()
                                    ->info('Resolve the tenant from the session.')
                                ->end()
                                ->booleanNode('route')
                                    ->defaultFalse()
                                    ->info('Resolve the tenant from a route parameter.')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('write_guard')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('check_user_access')
                                    ->defaultTrue()
                                    ->info('Also verify the current user is a member of the tenant on write.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        // @formatter:on

        return $treeBuilder;
    }
}
