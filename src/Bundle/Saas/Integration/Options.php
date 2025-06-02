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

namespace SolidWorx\Platform\SaasBundle\Integration;

class Options
{
    public const string SKIP_TRIAL = 'skipTrial';

    public const string EMAIL = 'email';

    protected array $options = [];

    private function __construct()
    {
    }

    public static function new(): self
    {
        return new self();
    }

    public function withEmail(string $email): self
    {
        $this->options[self::EMAIL] = $email;
        return $this;
    }

    public function withSkipTrial(bool $skipTrial): self
    {
        $this->options[self::SKIP_TRIAL] = $skipTrial;
        return $this;
    }

    public function withOption(string $option, mixed $value): self
    {
        $this->options[$option] = $value;
        return $this;
    }

    public function hasOption(string $option): bool
    {
        return isset($this->options[$option]);
    }

    public function getValue(string $option): mixed
    {
        return $this->options[$option] ?? null;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
