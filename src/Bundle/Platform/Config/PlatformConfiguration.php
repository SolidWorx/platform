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
use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ProfileType;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserTenantInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Form\FormTypeInterface;
use function is_string;
use function is_subclass_of;
use function sprintf;

final class PlatformConfiguration implements PlatformConfigurationInterface
{
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
                ->append($this->profileNode())
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

    /**
     * The `platform.profile` section: the pages where a user maintains their own account.
     *
     * Both extension points live here. `form_type` swaps the edit form out for one that knows
     * about a custom user class, and `templates` swaps any of the three pages out for a template
     * of your own — although overriding a block of the shipped template is usually enough, and
     * survives platform upgrades better.
     */
    private function profileNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('profile');

        // @formatter:off
        $node
            ->info('The user profile pages: details, edit, and change password.')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('form_type')
                    ->defaultValue(ProfileType::class)
                    ->info(sprintf('The form type used to edit the profile. Must implement %s', FormTypeInterface::class))
                    ->validate()
                        ->ifTrue(static fn ($v): bool => ! is_string($v) || ! is_subclass_of($v, FormTypeInterface::class))
                        ->thenInvalid(sprintf('The profile form type must implement %s', FormTypeInterface::class))
                    ->end()
                ->end()
                ->arrayNode('templates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('show')
                            ->defaultValue('@SolidWorxPlatform/Profile/show.html.twig')
                            ->info('The template showing the profile details.')
                        ->end()
                        ->scalarNode('edit')
                            ->defaultValue('@SolidWorxPlatform/Profile/edit.html.twig')
                            ->info('The template rendering the edit-profile form.')
                        ->end()
                        ->scalarNode('change_password')
                            ->defaultValue('@SolidWorxPlatform/Profile/change_password.html.twig')
                            ->info('The template rendering the change-password form.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('password')
                    ->info('The rules a user-chosen password has to satisfy.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('min_length')
                            ->defaultValue(12)
                            ->min(1)
                            ->info('The minimum number of characters a password must have.')
                        ->end()
                        ->enumNode('strength')
                            ->values(PasswordStrengthLevel::values())
                            ->defaultValue(PasswordStrengthLevel::Medium->value)
                            ->info('The estimated strength a password must reach; "none" disables the check.')
                        ->end()
                        ->booleanNode('check_compromised')
                            ->defaultTrue()
                            ->info('Reject passwords that appear in a known data breach (calls the haveibeenpwned range API).')
                        ->end()
                    ->end()
                ->end()
            ->end();
        // @formatter:on

        return $node;
    }
}
