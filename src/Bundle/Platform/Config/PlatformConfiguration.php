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
use SolidWorx\Platform\PlatformBundle\Form\Type\Tenant\TenantOnboardingType;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserTenantInterface;
use SolidWorx\Platform\PlatformBundle\Security\EmailLinkContext;
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantCreationVoter;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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
                        ->scalarNode('base_url')
                            ->defaultNull()
                            ->info('The URL emailed links (password reset, email verification) start with, e.g. https://app.example.com. Accepts %env()%, resolved at runtime. Leave null to use framework.router.default_uri. Set to "" (or leave both empty) to use the host of the request instead, e.g. for per-tenant domains; that host then has to be restricted with framework.trusted_hosts outside debug mode.')
                            ->validate()
                                // An %env()% value is only known at runtime, where EmailLinkContext checks it.
                                ->ifTrue(static fn (mixed $v): bool => $v !== null && (! is_string($v) || ($v !== '' && ! str_contains($v, '%env(') && ! EmailLinkContext::isValidBaseUrl($v))))
                                ->thenInvalid('The base URL must start with http:// or https:// and a host, got %s.')
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
                        ->arrayNode('email_verification')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultTrue()
                                    ->info('Send new users a verification link, show unverified users a banner, and gate actions through EmailVerificationGateInterface.')
                                ->end()
                                ->integerNode('lifetime')
                                    ->defaultValue(604800)
                                    ->min(1)
                                    ->info('How long, in seconds, a verification link stays valid. Defaults to 7 days.')
                                ->end()
                                ->scalarNode('firewall')
                                    ->defaultValue('main')
                                    ->info('The firewall whose form login signs the user in once they verify, and whose login page they land on when a link is invalid.')
                                ->end()
                                ->scalarNode('banner_template')
                                    ->defaultValue('@SolidWorxPlatform/EmailVerification/banner.html.twig')
                                    ->info('The banner shown to unverified users. Extend the default and override its blocks to change the message.')
                                ->end()
                                ->arrayNode('email')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('template')
                                            ->defaultValue('@SolidWorxPlatform/EmailVerification/email.html.twig')
                                            ->info('The HTML template of the verification email.')
                                        ->end()
                                        ->scalarNode('subject')
                                            ->defaultValue('Please confirm your email address')
                                            ->info('The subject of the verification email (translated).')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('reset_password')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultFalse()
                                    ->info('Let users who forgot their password reset it through a link sent to their email address. Needs the reset_password_request table.')
                                ->end()
                                ->integerNode('lifetime')
                                    ->defaultValue(3600)
                                    ->min(60)
                                    ->info('How long, in seconds, a reset link stays valid. Defaults to 1 hour.')
                                ->end()
                                ->integerNode('throttle_limit')
                                    ->defaultValue(900)
                                    ->min(0)
                                    ->info('How long, in seconds, a user has to wait before another reset link is sent to them. Defaults to 15 minutes.')
                                ->end()
                                ->booleanNode('enable_garbage_collection')
                                    ->defaultTrue()
                                    ->info('Delete expired reset requests whenever a new one is made. Turn off to clean up with the reset-password:remove-expired command instead.')
                                ->end()
                                ->scalarNode('firewall')
                                    ->defaultValue('main')
                                    ->info('The firewall whose login page links to the reset form, and where the user lands after choosing a new password.')
                                ->end()
                                ->arrayNode('rate_limit')
                                    ->addDefaultsIfNotSet()
                                    ->info('Limits how many reset links one IP address can ask for, whichever accounts they are for.')
                                    ->children()
                                        ->booleanNode('enabled')
                                            ->defaultTrue()
                                        ->end()
                                        ->integerNode('limit')
                                            ->defaultValue(5)
                                            ->min(1)
                                            ->info('Requests allowed per interval.')
                                        ->end()
                                        ->scalarNode('interval')
                                            ->defaultValue('15 minutes')
                                            ->cannotBeEmpty()
                                            ->info('The window the limit applies to, as a relative date string.')
                                        ->end()
                                    ->end()
                                ->end()
                                ->arrayNode('templates')
                                    ->addDefaultsIfNotSet()
                                    ->info('Extend the defaults and override their blocks, or replace them outright.')
                                    ->children()
                                        ->scalarNode('request')
                                            ->defaultValue('@SolidWorxPlatform/ResetPassword/request.html.twig')
                                            ->info('The page asking for the email address.')
                                        ->end()
                                        ->scalarNode('check_email')
                                            ->defaultValue('@SolidWorxPlatform/ResetPassword/check_email.html.twig')
                                            ->info('The page shown once the form is sent, whether or not the account exists.')
                                        ->end()
                                        ->scalarNode('reset')
                                            ->defaultValue('@SolidWorxPlatform/ResetPassword/reset.html.twig')
                                            ->info('The page the link opens, where the new password is chosen.')
                                        ->end()
                                    ->end()
                                ->end()
                                ->arrayNode('email')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('template')
                                            ->defaultValue('@SolidWorxPlatform/ResetPassword/email.html.twig')
                                            ->info('The HTML template of the reset email.')
                                        ->end()
                                        ->scalarNode('subject')
                                            ->defaultValue('Reset your password')
                                            ->info('The subject of the reset email (translated).')
                                        ->end()
                                    ->end()
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
                        ->scalarNode('layout')
                            ->defaultValue('@SolidWorxPlatform/Profile/layout.html.twig')
                            ->info('The layout every profile page extends, exposed to Twig as the "profile_layout" global. It renders the profile navigation beside a "profile_content" block.')
                        ->end()
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
