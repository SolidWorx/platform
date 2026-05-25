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

use Rector\Config\RectorConfig;
use Rector\Doctrine\Bundle230\Rector\Class_\AddAnnotationToRepositoryRector;
use SolidWorx\Platform\Tools\Rector\Rules\AddGenericMethodPhpDocRector;
use SolidWorx\Platform\Tools\Rector\Rules\AddGenericTemplateExtendsRector;
use SolidWorx\Platform\Tools\Rector\Rules\EnforcePlatformEntityRepositoryRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rules([
        EnforcePlatformEntityRepositoryRector::class,
        AddGenericMethodPhpDocRector::class,
        AddGenericTemplateExtendsRector::class,
    ]);

    $rectorConfig->skip([
        AddAnnotationToRepositoryRector::class,
    ]);
};
