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

namespace SolidWorx\Platform\PlatformBundle\Security\ResetPassword;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Repository\ResetPasswordRequestRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

/**
 * One outstanding password reset: the selector and the hash of the token mailed to the user.
 *
 * The plain token is never stored, so a leaked table cannot be used to reset anybody's password.
 * This directory is only mapped while `platform.security.reset_password.enabled` is true, so the
 * table only exists in applications that use the feature.
 */
#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
#[ORM\Table(name: self::TABLE_NAME)]
// Every link is looked up by its selector.
#[ORM\Index(columns: ['selector'])]
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
    use ResetPasswordRequestTrait;

    final public const string TABLE_NAME = 'reset_password_request';

    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private Ulid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private UserInterface $user,
        DateTimeInterface $expiresAt,
        string $selector,
        string $hashedToken,
    ) {
        $this->id = new Ulid();
        $this->initialize($expiresAt, $selector, $hashedToken);
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }
}
