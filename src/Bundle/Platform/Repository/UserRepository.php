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

namespace SolidWorx\Platform\PlatformBundle\Repository;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use SolidWorx\Platform\PlatformBundle\Contracts\Doctrine\Repository\UserRepository as UserRepositoryInterface;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Security\EventListener\DisabledUserLoginListener;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use function is_a;
use function is_object;
use function is_subclass_of;
use function sprintf;

/**
 * @template T of User
 * @template-extends EntityRepository<T>
 * @template-implements UserProviderInterface<T>
 */
class UserRepository extends EntityRepository implements UserRepositoryInterface, UserLoaderInterface, UserProviderInterface
{
    /**
     * @param class-string<T> $userClass
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(param: 'solidworx_platform.models.user')]
        string $userClass = \SolidWorx\Platform\PlatformBundle\Entity\User::class,
    ) {
        parent::__construct($registry, $userClass);
    }

    /**
     * Called on every request of a signed-in user. Throwing here logs a disabled user out, so
     * disabling an account takes effect immediately rather than when the session expires.
     */
    #[Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        $class = $user::class;
        if (! $user instanceof User || ! $this->supportsClass($class)) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $class));
        }

        // By id, not email: the email may have changed since the session started.
        $refreshed = $this->find($user->getId());

        if (! $refreshed instanceof User || ! $refreshed->isEnabled()) {
            throw new UserNotFoundException(sprintf('User "%s" does not exist or is disabled.', $user->getId()));
        }

        return $refreshed;
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        if ($this->getEntityName() === $class) {
            return true;
        }

        return is_subclass_of($class, $this->getEntityName());
    }

    /**
     * Disabled users are loaded too, so that sign-in can tell them their account is disabled once
     * their password checks out; {@see DisabledUserLoginListener} refuses them.
     *
     * @return T
     */
    #[Override]
    public function loadUserByIdentifier(string $identifier): User
    {
        $q = $this
            ->createQueryBuilder('u')
            ->select('u')
            ->where('(u.email = :email)')
            ->setParameter('email', $identifier)
            ->getQuery();

        try {
            // The Query::getSingleResult() method throws an exception if there is no record matching the criteria.
            $user = $q->getSingleResult();
        } catch (NoResultException | NonUniqueResultException $e) {
            throw new UserNotFoundException(sprintf('User "%s" does not exist.', $identifier), 0, $e);
        }

        if (! is_object($user) || ! is_a($user, $this->getEntityName())) {
            throw new UserNotFoundException(sprintf('User "%s" does not exist.', $identifier));
        }

        return $user;
    }
}
