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

namespace SolidWorx\Platform\PlatformBundle\Config\Builder;

use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;

/**
 * Builds the `platform.profile` section.
 *
 *     PlatformConfigBuilder::create()
 *         ->profile()
 *             ->formType(App\Form\ProfileType::class)
 *             ->showTemplate('@App/profile/show.html.twig')
 *             ->passwordMinLength(16)
 *             ->passwordStrength(PasswordStrengthLevel::Strong)
 *         ->end()
 *         ->build();
 */
final class ProfileConfigBuilder
{
    private ?string $formType = null;

    /**
     * @var array<string, string>
     */
    private array $templates = [];

    /**
     * @var array<string, bool|int|string>
     */
    private array $password = [];

    private function __construct(
        private readonly PlatformConfigBuilder $parent
    ) {
    }

    public static function create(PlatformConfigBuilder $parent): self
    {
        return new self($parent);
    }

    /**
     * @param class-string $formType
     */
    public function formType(string $formType): self
    {
        $this->formType = $formType;
        return $this;
    }

    public function showTemplate(string $template): self
    {
        $this->templates['show'] = $template;
        return $this;
    }

    public function editTemplate(string $template): self
    {
        $this->templates['edit'] = $template;
        return $this;
    }

    public function changePasswordTemplate(string $template): self
    {
        $this->templates['change_password'] = $template;
        return $this;
    }

    public function passwordMinLength(int $length): self
    {
        $this->password['min_length'] = $length;
        return $this;
    }

    public function passwordStrength(PasswordStrengthLevel $strength): self
    {
        $this->password['strength'] = $strength->value;
        return $this;
    }

    public function checkCompromisedPassword(bool $check = true): self
    {
        $this->password['check_compromised'] = $check;
        return $this;
    }

    public function end(): PlatformConfigBuilder
    {
        return $this->parent;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $config = [];

        if ($this->formType !== null) {
            $config['form_type'] = $this->formType;
        }

        if ($this->templates !== []) {
            $config['templates'] = $this->templates;
        }

        if ($this->password !== []) {
            $config['password'] = $this->password;
        }

        return $config;
    }
}
