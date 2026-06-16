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

use EmailChecker\Adapter\AggregatorAdapter;
use EmailChecker\Adapter\BuiltInAdapter;
use EmailChecker\Adapter\FileAdapter;
use EmailChecker\Constraints\NotThrowawayEmailValidator;
use EmailChecker\EmailChecker;
use SolidWorx\Platform\PlatformBundle\Command\UpdateDisposableDomainsCommand;
use SolidWorx\Platform\PlatformBundle\Controller\Security\Login;
use SolidWorx\Platform\PlatformBundle\Feature\FeatureGate;
use SolidWorx\Platform\PlatformBundle\Feature\NoopFeatureGate;
use SolidWorx\Platform\PlatformBundle\Feature\NullSubscriberResolver;
use SolidWorx\Platform\PlatformBundle\Feature\SubscriberResolver;
use SolidWorx\Platform\PlatformBundle\SolidWorxPlatformBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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
        ->exclude(dirname(__DIR__, 2) . '/{DependencyInjection,Entity,Resources,Tests}');

    $services->set(Login::class)
        ->tag('controller.service_arguments');

    $services->alias(FeatureGate::class, NoopFeatureGate::class);
    $services->alias(SubscriberResolver::class, NullSubscriberResolver::class);

    // Disposable / throwaway email detection.
    //
    // The built-in domain list is layered with a supplemental, refreshable file
    // (kept current by the platform:disposable-domains:update command) so that
    // both the vendor baseline and locally tracked domains are matched.
    $blocklistFile = dirname(__DIR__) . '/data/disposable_email_blocklist.conf';

    $services->set(BuiltInAdapter::class);

    $services->set(FileAdapter::class)
        ->args([$blocklistFile]);

    $services->set(AggregatorAdapter::class)
        ->args([[service(BuiltInAdapter::class), service(FileAdapter::class)]]);

    $services->set(EmailChecker::class)
        ->args([service(AggregatorAdapter::class)]);

    // Register the package validator as a service so OUR EmailChecker (with the
    // aggregated list) is injected; otherwise Symfony instantiates it with the
    // built-in list only.
    $services->set(NotThrowawayEmailValidator::class)
        ->args([service(EmailChecker::class)])
        ->tag('validator.constraint_validator');

    $services->set(UpdateDisposableDomainsCommand::class)
        ->arg('$blocklistFile', $blocklistFile);
};
