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

namespace SolidWorx\Platform\PlatformBundle\Security\Authorization;

use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use function array_key_first;
use function count;
use function is_string;

/**
 * An access decision manager that can use a different strategy for a given attribute.
 *
 * Symfony decides everything with one strategy, and the default — `affirmative` — returns on the
 * first voter that grants, without looking at the rest. That is the wrong shape for a permission a
 * bundle ships: the platform's own voter grants, and an application voter that refuses (a plan cap,
 * an invite-only product) is never counted. Deciding that one attribute `unanimously` makes a
 * refusal decisive, while every other attribute in the application keeps the strategy the
 * application chose.
 *
 * It replaces the `security.access.decision_manager` definition rather than the service id, so
 * Symfony's own `AddSecurityVotersPass` still collects, sorts and (in debug) traces the voters into
 * argument 0, and the strategy the application configured still arrives in argument 1 as the
 * default.
 *
 * @see \SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass\AccessDecisionCompilerPass
 */
final class PerAttributeAccessDecisionManager implements AccessDecisionManagerInterface
{
    private readonly AccessDecisionManager $default;

    /**
     * @var array<string, AccessDecisionManager>
     */
    private array $managers = [];

    /**
     * @param iterable<mixed, VoterInterface>                $voters     Filled by `AddSecurityVotersPass`
     * @param array<string, AccessDecisionStrategyInterface> $strategies Keyed by the attribute they decide
     */
    public function __construct(
        iterable $voters = [],
        ?AccessDecisionStrategyInterface $strategy = null,
        array $strategies = [],
    ) {
        $this->default = new AccessDecisionManager($voters, $strategy);

        foreach ($strategies as $attribute => $attributeStrategy) {
            $this->managers[$attribute] = new AccessDecisionManager($voters, $attributeStrategy);
        }
    }

    /**
     * @param array<mixed> $attributes
     */
    #[Override]
    public function decide(TokenInterface $token, array $attributes, mixed $object = null, bool | AccessDecision | null $accessDecision = null, bool $allowMultipleAttributes = false): bool
    {
        return $this->managerFor($attributes)
            ->decide($token, $attributes, $object, $accessDecision, $allowMultipleAttributes);
    }

    /**
     * Only a decision about a single, named attribute can have its own strategy. A call carrying
     * several attributes — an `access_control` rule listing roles — is one decision about all of
     * them, and answering it with a strategy picked from one member would be arbitrary.
     *
     * @param array<mixed> $attributes
     */
    private function managerFor(array $attributes): AccessDecisionManager
    {
        if (count($attributes) !== 1) {
            return $this->default;
        }

        $attribute = $attributes[array_key_first($attributes)];

        if (! is_string($attribute)) {
            return $this->default;
        }

        return $this->managers[$attribute] ?? $this->default;
    }
}
