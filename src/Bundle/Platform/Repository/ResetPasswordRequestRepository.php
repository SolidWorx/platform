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

use DateTimeInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Security\ResetPassword\ResetPasswordRequest;
use Symfony\Bridge\Doctrine\Types\UlidType;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Persistence\Repository\ResetPasswordRequestRepositoryTrait;
use SymfonyCasts\Bundle\ResetPassword\Persistence\ResetPasswordRequestRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 * Where symfonycasts/reset-password-bundle keeps its requests. Removed from the container while
 * `platform.security.reset_password` is disabled.
 *
 * The trait's per-user queries bind the user entity as the parameter, which Doctrine turns into
 * the ULID's string form; the column holds it in binary, so nothing would ever match — no throttle,
 * and a spent link would stay valid. They are redefined here to bind the id as a `ulid`.
 *
 * @template-extends EntityRepository<ResetPasswordRequest>
 */
class ResetPasswordRequestRepository extends EntityRepository implements ResetPasswordRequestRepositoryInterface
{
    use ResetPasswordRequestRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetPasswordRequest::class);
    }

    public function createResetPasswordRequest(object $user, DateTimeInterface $expiresAt, string $selector, string $hashedToken): ResetPasswordRequest
    {
        Assert::isInstanceOf($user, UserInterface::class);

        return new ResetPasswordRequest($user, $expiresAt, $selector, $hashedToken);
    }

    public function getMostRecentNonExpiredRequestDate(object $user): ?DateTimeInterface
    {
        $request = $this->forUser($this->createQueryBuilder('t'), $user)
            ->orderBy('t.requestedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($request instanceof ResetPasswordRequest && ! $request->isExpired()) {
            return $request->getRequestedAt();
        }

        return null;
    }

    public function removeResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
        $this->removeRequests($resetPasswordRequest->getUser());
    }

    /**
     * Removes every request of the user, used and unused.
     */
    public function removeRequests(object $user): void
    {
        $this->forUser($this->createQueryBuilder('t')->delete(), $user)
            ->getQuery()
            ->execute();
    }

    private function forUser(QueryBuilder $queryBuilder, object $user): QueryBuilder
    {
        Assert::isInstanceOf($user, UserInterface::class);

        return $queryBuilder
            ->where('t.user = :user')
            ->setParameter('user', $user->getId(), UlidType::NAME);
    }
}
