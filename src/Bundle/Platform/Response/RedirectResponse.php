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

namespace SolidWorx\Platform\PlatformBundle\Response;

use SolidWorx\Platform\PlatformBundle\Enum\Flash;
use Symfony\Component\HttpFoundation\RedirectResponse as BaseRedirectResponse;

final class RedirectResponse extends BaseRedirectResponse
{
    /**
     * @var array<array{type: Flash, message: string}>
     */
    private array $flashes = [];

    public function withFlash(Flash $type, string $message): self
    {
        $this->flashes[] = [
            'type' => $type,
            'message' => $message,
        ];

        return $this;
    }

    /**
     * @return array<array{type: Flash, message: string}>
     */
    public function getFlashes(): array
    {
        return $this->flashes;
    }
}
