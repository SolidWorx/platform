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

namespace SolidWorx\Platform\PlatformBundle\Security\Voter;

use Symfony\Component\Uid\Ulid;
use Override;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants access to a {@see Tenant} when the authenticated user is a member of it.
 *
 * @extends Voter<string, TenantInterface>
 */
final class TenantVoter extends Voter
{
    public const string TENANT_ACCESS = 'TENANT_ACCESS';

    public function __construct(
        private readonly UserTenantRepository $userTenantRepository,
    ) {
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::TENANT_ACCESS && $subject instanceof TenantInterface;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (! $user instanceof User) {
            return false;
        }

        $userId = $user->getId();

        if (!$userId instanceof Ulid) {
            return false;
        }

        return $subject instanceof TenantInterface && $this->userTenantRepository->hasAccess($userId, $subject);
    }
}
