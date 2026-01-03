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
use SolidWorx\Platform\PlatformBundle\Contracts\Doctrine\Repository\UserRepository as UserRepositoryInterface;
use SolidWorx\Platform\PlatformBundle\Model\User;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use function assert;
use function is_subclass_of;
use function sprintf;

/**
 * @template T
 * @extends EntityRepository<T>
 */
class UserRepository extends EntityRepository implements UserRepositoryInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry, ?string $className = User::class)
    {
        parent::__construct($registry, $className);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        $class = $user::class;
        if (! $this->supportsClass($class)) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $class));
        }

        assert($user instanceof User);

        return $this->loadUserByIdentifier($user->getEmail());
    }

    public function supportsClass(string $class): bool
    {
        return $this->getEntityName() === $class || is_subclass_of($class, $this->getEntityName());
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $q = $this
            ->createQueryBuilder('u')
            ->select('u')
            ->where('(u.email = :email)')
            ->andWhere('u.enabled = :enabled')
            ->setParameter('email', $identifier)
            ->setParameter('enabled', true)
            ->getQuery();

        try {
            // The Query::getSingleResult() method throws an exception if there is no record matching the criteria.
            return $q->getSingleResult();
        } catch (NoResultException|NonUniqueResultException $e) {
            throw new UserNotFoundException(sprintf('User "%s" does not exist.', $identifier), 0, $e);
        }
    }
}
