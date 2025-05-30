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

use Doctrine\ORM\Events;
use SolidWorx\Platform\SaasBundle\Doctrine\EventSubscriber\MetadataSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private();

    $services->set(MetadataSubscriber::class)
        ->tag('doctrine.event_listener', [
            'event' => Events::loadClassMetadata,
        ]);
};
