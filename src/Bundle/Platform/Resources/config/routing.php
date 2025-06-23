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

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $configurator): void {
    $configurator
        ->add('2fa_login', '/2fa')
        ->controller('scheb_two_factor.form_controller::form')
    ;
    $configurator
        ->add('2fa_login_check', '/2fa_check')
    ;

    $configurator->import('../../Controller/', 'attribute');
};
