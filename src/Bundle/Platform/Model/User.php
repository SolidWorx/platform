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

namespace SolidWorx\Platform\PlatformBundle\Model;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\PlatformBundle\Contracts\Security\TwoFactor\UserTwoFactorInterface;
use SolidWorx\Platform\PlatformBundle\Security\TwoFactor\Traits\UserTwoFactor;
use Stringable;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use function array_search;
use function array_unique;
use function array_values;
use function in_array;
use function strtoupper;

#[UniqueEntity(fields: ['email'], message: 'This email is already in use. Do you want to log in instead?')]
#[ORM\Index(fields: ['googleId'])]
abstract class User implements UserInterface, PasswordAuthenticatedUserInterface, Stringable, UserTwoFactorInterface
{
    // public const string TABLE_NAME = 'users';

    use UserTwoFactor;

    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    protected ?Ulid $id = null;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 45, nullable: true)]
    #[Assert\NotBlank()]
    protected ?string $firstName = null;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 45, nullable: true)]
    #[Assert\NotBlank()]
    protected ?string $lastName = null;

    #[ORM\Column(name: 'mobile', type: Types::STRING, nullable: true)]
    protected ?string $mobile = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 180, unique: true)]
    #[Assert\NotBlank()]
    #[Assert\Email(
        message: 'The email "{{ value }}" is not a valid email address.',
        mode: Assert\Email::VALIDATION_MODE_STRICT,
    )]
    protected ?string $email = null;

    #[ORM\Column(name: 'enabled', type: Types::BOOLEAN)]
    protected bool $enabled = false;

    #[ORM\Column(name: 'verified', type: Types::BOOLEAN)]
    protected bool $verified = false;

    #[ORM\Column(name: 'password', type: Types::STRING)]
    protected ?string $password = null;

    #[ORM\Column(name: 'last_login', type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?DateTimeInterface $lastLogin = null;

    /**
     * @var string[]
     */
    #[ORM\Column(name: 'roles', type: 'array')]
    protected array $roles = [];

    #[ORM\Column(name: 'google_id', type: Types::STRING, length: 45, nullable: true)]
    protected ?string $googleId = null;

    public function __construct()
    {
        $this->id = new NilUlid();
    }

    public function __toString(): string
    {
        return $this->email;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(string $mobile): static
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function addRole(string $role): static
    {
        $role = strtoupper($role);
        if ($role === 'ROLE_USER') {
            return $this;
        }

        if (! in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getLastLogin(): ?DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;

        // we need to make sure to have at least one role
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function hasRole(string $role): bool
    {
        return in_array(strtoupper($role), $this->getRoles(), true);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function removeRole(string $role): static
    {
        if (false !== $key = array_search(strtoupper($role), $this->roles, true)) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }

        return $this;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function setLastLogin(?DateTimeInterface $time = null): static
    {
        $this->lastLogin = $time;

        return $this;
    }

    /**
     * @param string[] $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = [];

        foreach ($roles as $role) {
            $this->addRole($role);
        }

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }
}
