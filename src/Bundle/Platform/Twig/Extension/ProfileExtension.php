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

namespace SolidWorx\Platform\PlatformBundle\Twig\Extension;

use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes the configured profile layout to Twig.
 *
 * Profile pages extend `profile_layout` rather than hard-coding
 * `@SolidWorxPlatform/Profile/layout.html.twig`, so an application can swap the whole profile
 * chrome through `platform.profile.templates.layout` and every page follows — including its own.
 *
 * It is a Twig extension rather than an entry under `twig.globals` for a reason worth keeping:
 * template names start with `@`, and a string starting with `@` in the container is a service
 * reference. Passing one through `twig.globals` makes the container look for a service called
 * `SolidWorxPlatform/Profile/layout.html.twig` and fail at compile time. A constructor argument
 * has no such meaning, which is also why the UI bundle exposes its layouts this way.
 */
final class ProfileExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        #[Autowire(param: 'solidworx_platform.profile.templates.layout')]
        private readonly string $layout,
    ) {
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function getGlobals(): array
    {
        return [
            'profile_layout' => $this->layout,
        ];
    }
}
