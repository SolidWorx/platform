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

use SolidWorx\Platform\PlatformBundle\Controller\Security\Login;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureGate;
use SolidWorx\Platform\PlatformBundle\Feature\NoopFeatureGate;
use SolidWorx\Platform\PlatformBundle\Feature\NullSubscriberResolver;
use SolidWorx\Platform\PlatformBundle\Feature\SubscriberResolver;
use SolidWorx\Platform\PlatformBundle\SolidWorxPlatformBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private()
    ;

    $services
        ->load(SolidWorxPlatformBundle::NAMESPACE . '\\', dirname(__DIR__, 2))
        ->exclude(dirname(__DIR__, 2) . '/{DependencyInjection,Entity,Resources,Tests,Tenant/Event,Doctrine/Filter,Messenger}');

    $services->set(Login::class)
        ->tag('controller.service_arguments');

    $services->alias(FeatureGate::class, NoopFeatureGate::class);
    $services->alias(SubscriberResolver::class, NullSubscriberResolver::class);
};
