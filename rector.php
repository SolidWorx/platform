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
use Rector\DeadCode\Rector\Class_\RemoveRefactorDuplicatedNodeInstanceCheckRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Configs\Rector\Closure\ServiceArgsToServiceNamedArgRector;
use Rector\Symfony\Configs\Rector\Closure\ServiceSettersToSettersAutodiscoveryRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnDocblockForScalarArrayFromAssignsRector;
use Rector\TypeDeclarationDocblocks\Rector\Class_\AddParamTypeToRefactorMethodRector;
use Rector\ValueObject\PhpVersion;
use SolidWorx\Platform\Tools\Rector\Set\SolidWorxSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withImportNames()
    ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withAttributesSets(symfony: true, doctrine: true, gedmo: true, phpunit: true)
    ->withPhpLevel(0)
    ->withRootFiles()
    ->withEditorUrl('phpstorm://open?url=file://%%f&line=%%l')
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        naming: false,
        namedArgs: true,
        instanceOf: true,
        if: true,
        earlyReturn: true,
        carbon: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
        phpunitNarrowAsserts: true,
        phpunitMockToStub: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
        symfonyConfigs: true,
    )
    ->withParallel()
    ->withSets([
        SolidWorxSetList::PLATFORM,
        // General
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::RECTOR_PRESET,
        SetList::TYPE_DECLARATION,
        SetList::TYPE_DECLARATION_DOCBLOCKS,
        SetList::CARBON,
        SetList::PRIVATIZATION,

        // PHP
        LevelSetList::UP_TO_PHP_84,

        // PHPUnit
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_MOCK_TO_STUB,
        PHPUnitSetList::PHPUNIT_NARROW_ASSERTS,
        PHPUnitSetList::COMPOSER_BASED,
    ])
    ->withSkip([
        AddReturnDocblockForScalarArrayFromAssignsRector::class,
        RemoveRefactorDuplicatedNodeInstanceCheckRector::class,
        AddParamTypeToRefactorMethodRector::class,

        /**
         * This rule changes Symfony service config
         * from `$services->set(SomeClass::class)->args(['some_value']);` to `$services->set(SomeClass::class)->arg('$someCtorParameter', 'some_value');`
         * which means that if we change the variable name in the constructor,
         * the service definition will break.
         */
        ServiceArgsToServiceNamedArgRector::class,

        /**
         * In `Resources/config/services.php` this rule replaces explicit registrations for classes
         * outside the bundle's own autoloaded namespace (e.g. `EmailChecker\Adapter\BuiltInAdapter`,
         * `Symfony\Component\Process\ExecutableFinder`) with a directory-based `$services->load()`
         * pointed at the third-party vendor package's source tree — deleting the registrations
         * those services actually need and adding a load() call against a path that was never meant
         * to be autodiscovered. Confirmed by running the rule for real: it broke the container.
         */
        ServiceSettersToSettersAutodiscoveryRector::class,
    ]);
