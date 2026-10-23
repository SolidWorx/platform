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

use DateTimeImmutable;
use SymfonyCasts\Bundle\ResetPassword\Exception\InvalidResetPasswordTokenException;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use function str_repeat;
use function time;

/**
 * Stands in for the bundle's helper, which needs a database: it issues one fixed token, can be
 * told to throttle or to reject, and records which tokens were spent.
 */
final class FakeResetPasswordHelper implements ResetPasswordHelperInterface
{
    public const string TOKEN = 'selectorselectorselecverifierverifierver';

    public bool $throttled = false;

    public ?ResetPasswordExceptionInterface $rejection = null;

    /**
     * @var list<string>
     */
    public array $removed = [];

    /**
     * @var list<object>
     */
    public array $issuedFor = [];

    public function __construct(
        public ?object $user = null,
    ) {
    }

    public function generateResetToken(object $user, ?int $resetRequestLifetime = null): ResetPasswordToken
    {
        if ($this->throttled) {
            throw new TooManyPasswordRequestsException(new DateTimeImmutable('+15 minutes'));
        }

        $this->issuedFor[] = $user;

        return new ResetPasswordToken(self::TOKEN, new DateTimeImmutable('+1 hour'), time());
    }

    public function validateTokenAndFetchUser(string $fullToken): object
    {
        if ($this->rejection instanceof ResetPasswordExceptionInterface) {
            throw $this->rejection;
        }

        if ($fullToken !== self::TOKEN || $this->user === null) {
            throw new InvalidResetPasswordTokenException();
        }

        return $this->user;
    }

    public function removeResetRequest(string $fullToken): void
    {
        $this->removed[] = $fullToken;
    }

    public function getTokenLifetime(): int
    {
        return 3600;
    }

    public function generateFakeResetToken(?int $resetRequestLifetime = null): ResetPasswordToken
    {
        return new ResetPasswordToken(str_repeat('f', 32), new DateTimeImmutable('+1 hour'), time());
    }
}
