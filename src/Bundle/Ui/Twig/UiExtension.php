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

namespace SolidWorx\Platform\UiBundle\Twig;

use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class UiExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly string $baseTemplate,
        #[Autowire(param: 'solidworx_platform.app.name')]
        private readonly string $appName,
    ) {
    }

    #[Override]
    public function getGlobals(): array
    {
        return [
            'ui_base_template' => $this->baseTemplate,
            'ui_app_name' => $this->appName,
        ];
    }
}
