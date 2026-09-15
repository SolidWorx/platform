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

namespace SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass;

use LogicException;
use Override;
use SolidWorx\Platform\PlatformBundle\Security\Authorization\PerAttributeAccessDecisionManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\ConsensusStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\PriorityStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use function get_debug_type;
use function is_array;
use function sprintf;

/**
 * Teaches Symfony's access decision manager to decide certain attributes with their own strategy.
 *
 * It rewrites the `security.access.decision_manager` *definition* rather than pointing the service
 * id elsewhere, which matters: `AddSecurityVotersPass` gives up when that id is an alias, and with
 * it go the collected voters and their debug tracing. Rewriting the definition keeps Symfony's own
 * wiring intact — the voters still arrive in argument 0, the strategy the application configured
 * still arrives in argument 1, and this pass only adds argument 2.
 *
 * @see PerAttributeAccessDecisionManager
 */
final class AccessDecisionCompilerPass implements CompilerPassInterface
{
    private const string PARAMETER = 'solidworx_platform.security.access_decision.strategies';

    private const string MANAGER_ID = 'security.access.decision_manager';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter(self::PARAMETER)) {
            return;
        }

        $strategies = $container->getParameter(self::PARAMETER);

        if (! is_array($strategies) || $strategies === []) {
            return;
        }

        if (! $container->hasDefinition(self::MANAGER_ID)) {
            // The application pointed `security.access_decision_manager.service` at a manager of its
            // own, so this would be silently ignored. Attribute strategies are a security decision:
            // say so rather than let a refusal quietly stop counting.
            throw new LogicException(sprintf(
                'The platform decides some attributes with their own access decision strategy, which needs Symfony\'s own "%s" service. Your application replaced it through "security.access_decision_manager.service"; either drop that, or empty "platform.security.access_decision.strategies" and handle those attributes in your own manager.',
                self::MANAGER_ID,
            ));
        }

        $definition = $container->getDefinition(self::MANAGER_ID);
        $definition->setClass(PerAttributeAccessDecisionManager::class);

        $byAttribute = [];

        foreach ($strategies as $attribute => $strategy) {
            $byAttribute[(string) $attribute] = match ($strategy) {
                'affirmative' => new Definition(AffirmativeStrategy::class),
                'consensus' => new Definition(ConsensusStrategy::class),
                'unanimous' => new Definition(UnanimousStrategy::class),
                'priority' => new Definition(PriorityStrategy::class),
                default => throw new LogicException(sprintf('Unsupported access decision strategy "%s" for attribute "%s".', get_debug_type($strategy), (string) $attribute)),
            };
        }

        // Argument 0 is the voter iterator and argument 1 the application's default strategy, both
        // written by Symfony. Only argument 2 is ours, so this pass is order-independent with
        // regard to AddSecurityVotersPass.
        $definition->setArgument(2, $byAttribute);
    }
}
