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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures;

use Override;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use function is_a;

/**
 * Serves one {@see ProfileUser}, the same instance every time.
 *
 * A test that drives a live component over HTTP crosses a request boundary, so the user the
 * component mutates has to be the same object the test asserts against — an in-memory provider
 * would hand back a fresh `InMemoryUser` instead.
 *
 * @implements UserProviderInterface<ProfileUser>
 */
final class StubUserProvider implements UserProviderInterface
{
    private readonly ProfileUser $user;

    public function __construct()
    {
        $this->user = new ProfileUser();
        $this->user
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setEmail('ada@example.com');
    }

    public function user(): ProfileUser
    {
        return $this->user;
    }

    #[Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->user;
    }

    #[Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (! $user instanceof ProfileUser) {
            throw new UnsupportedUserException();
        }

        return $this->user;
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        return is_a($class, ProfileUser::class, true);
    }
}
