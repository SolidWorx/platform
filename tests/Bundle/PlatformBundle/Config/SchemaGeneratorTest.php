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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Config;

use Closure;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfiguration;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigurationInterface;
use SolidWorx\Platform\PlatformBundle\Config\SchemaGenerator;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\UiBundle\Config\UiConfiguration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

#[CoversClass(SchemaGenerator::class)]
final class SchemaGeneratorTest extends TestCase
{
    public function testGenerateReturnsTopLevelSchemaStructure(): void
    {
        $generator = new SchemaGenerator([]);
        $schema = $generator->generate();

        self::assertArrayHasKey('$schema', $schema);
        self::assertArrayHasKey('title', $schema);
        self::assertArrayHasKey('description', $schema);
        self::assertArrayHasKey('type', $schema);
        self::assertArrayHasKey('properties', $schema);
        self::assertSame('object', $schema['type']);
    }

    public function testGenerateJsonSchemaVersion(): void
    {
        $generator = new SchemaGenerator([]);
        $schema = $generator->generate();

        self::assertSame('https://json-schema.org/draft/2020-12/schema', $schema['$schema']);
    }

    public function testGenerateHasPlatformProperty(): void
    {
        $generator = new SchemaGenerator([]);
        $schema = $generator->generate();

        self::assertArrayHasKey('platform', $schema['properties']);
        self::assertSame('object', $schema['properties']['platform']['type']);
    }

    public function testGenerateAllowsAdditionalTopLevelProperties(): void
    {
        $generator = new SchemaGenerator([]);
        $schema = $generator->generate();

        self::assertTrue($schema['additionalProperties']);
    }

    public function testGeneratePlatformDisallowsAdditionalProperties(): void
    {
        $generator = new SchemaGenerator([]);
        $schema = $generator->generate();

        self::assertFalse($schema['properties']['platform']['additionalProperties']);
    }

    public function testEmptyConfigurationsProducesEmptyPlatformProperties(): void
    {
        $generator = new SchemaGenerator([]);
        $schema = $generator->generate();

        self::assertSame([], $schema['properties']['platform']['properties']);
    }

    public function testRootSectionKeyMergesChildrenIntoPlatformProperties(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->scalarNode('app_name')->defaultValue('Test')->end()
                ->booleanNode('debug')->defaultFalse()->end()
            ->end();
        });

        $generator = new SchemaGenerator([$config]);
        $schema = $generator->generate();

        $properties = $schema['properties']['platform']['properties'];
        self::assertArrayHasKey('app_name', $properties);
        self::assertArrayHasKey('debug', $properties);
        self::assertArrayNotHasKey('root', $properties);
    }

    public function testNonEmptySectionKeyAddsRootLevelProperty(): void
    {
        $config = $this->makeConfig('mymodule', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->scalarNode('setting')->end()
            ->end();
        });

        $generator = new SchemaGenerator([$config]);
        $schema = $generator->generate();

        self::assertArrayHasKey('mymodule', $schema['properties']);
        self::assertArrayNotHasKey('mymodule', $schema['properties']['platform']['properties']);
        self::assertSame('object', $schema['properties']['mymodule']['type']);
    }

    public function testScalarNodeGeneratesStringType(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->scalarNode('title')->defaultValue('hello')->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['title'];

        self::assertSame('string', $prop['type']);
    }

    public function testNullableScalarNodeGeneratesStringNullType(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->scalarNode('template')->defaultNull()->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['template'];

        self::assertSame(['string', 'null'], $prop['type']);
    }

    public function testBooleanNodeGeneratesBooleanType(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->booleanNode('enabled')->defaultFalse()->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['enabled'];

        self::assertSame('boolean', $prop['type']);
    }

    public function testIntegerNodeGeneratesIntegerType(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->integerNode('count')->defaultValue(10)->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['count'];

        self::assertSame('integer', $prop['type']);
    }

    public function testFloatNodeGeneratesNumberType(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->floatNode('ratio')->defaultValue(1.5)->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['ratio'];

        self::assertSame('number', $prop['type']);
    }

    public function testEnumNodeGeneratesStringTypeWithEnumValues(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->enumNode('color')->values(['red', 'green', 'blue'])->defaultValue('red')->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['color'];

        self::assertSame('string', $prop['type']);
        self::assertSame(['red', 'green', 'blue'], $prop['enum']);
    }

    public function testVariableNodeGeneratesNoTypeConstraint(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->variableNode('anything')->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['anything'];

        self::assertArrayNotHasKey('type', $prop);
    }

    public function testArrayNodeGeneratesObjectType(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->arrayNode('nested')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('key')->defaultValue('val')->end()
                    ->end()
                ->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $nested = $schema['properties']['platform']['properties']['nested'];

        self::assertSame('object', $nested['type']);
        self::assertArrayHasKey('properties', $nested);
        self::assertArrayHasKey('key', $nested['properties']);
    }

    public function testArrayNodeSetsAdditionalPropertiesToFalse(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->arrayNode('options')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('x')->end()
                    ->end()
                ->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $options = $schema['properties']['platform']['properties']['options'];

        self::assertFalse($options['additionalProperties']);
    }

    public function testRequiredChildNodesPopulateRequiredArray(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->arrayNode('db')
                    ->children()
                        ->scalarNode('host')->isRequired()->end()
                        ->scalarNode('port')->defaultValue('5432')->end()
                    ->end()
                ->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $db = $schema['properties']['platform']['properties']['db'];

        self::assertArrayHasKey('required', $db);
        self::assertContains('host', $db['required']);
        self::assertNotContains('port', $db['required']);
    }

    public function testPrototypedArrayNodeWithKeyAttributeGeneratesAdditionalProperties(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->arrayNode('items')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('value')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $items = $schema['properties']['platform']['properties']['items'];

        self::assertSame('object', $items['type']);
        self::assertArrayHasKey('additionalProperties', $items);
        self::assertArrayNotHasKey('items', $items);
    }

    public function testPrototypedArrayNodeWithoutKeyAttributeGeneratesItemsSchema(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->arrayNode('tags')
                    ->scalarPrototype()->end()
                ->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $tags = $schema['properties']['platform']['properties']['tags'];

        self::assertSame('array', $tags['type']);
        self::assertArrayHasKey('items', $tags);
        self::assertArrayNotHasKey('additionalProperties', $tags);
    }

    public function testNodeInfoPopulatesDescription(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->scalarNode('name')
                    ->info('The application name.')
                    ->defaultValue('app')
                ->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['name'];

        self::assertSame('The application name.', $prop['description']);
    }

    public function testNodeWithoutInfoHasNoDescription(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->scalarNode('name')->defaultValue('app')->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['name'];

        self::assertArrayNotHasKey('description', $prop);
    }

    public function testDefaultValueIsIncludedInSchema(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->scalarNode('driver')->defaultValue('pdo_mysql')->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['driver'];

        self::assertSame('pdo_mysql', $prop['default']);
    }

    public function testBooleanDefaultFalseIsIncluded(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->booleanNode('active')->defaultFalse()->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['active'];

        self::assertArrayHasKey('default', $prop);
        self::assertFalse($prop['default']);
    }

    public function testRequiredNodeHasNoDefaultInSchema(): void
    {
        $config = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->children()
                ->scalarNode('required_field')->isRequired()->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config]))->generate();
        $prop = $schema['properties']['platform']['properties']['required_field'];

        self::assertArrayNotHasKey('default', $prop);
    }

    public function testMultipleConfigurationsAreMerged(): void
    {
        $config1 = $this->makeConfig('', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->scalarNode('name')->defaultValue('app')->end()
            ->end();
        });

        $config2 = $this->makeConfig('module', static function (ArrayNodeDefinition $root): void {
            $root->addDefaultsIfNotSet()->children()
                ->booleanNode('enabled')->defaultFalse()->end()
            ->end();
        });

        $schema = (new SchemaGenerator([$config1, $config2]))->generate();

        // Root section children go under platform:
        self::assertArrayHasKey('name', $schema['properties']['platform']['properties']);
        // Non-empty section keys are root-level siblings of platform:
        self::assertArrayHasKey('module', $schema['properties']);
        self::assertArrayNotHasKey('module', $schema['properties']['platform']['properties']);
    }

    public function testWithRealPlatformConfiguration(): void
    {
        $generator = new SchemaGenerator([new PlatformConfiguration()]);
        $schema = $generator->generate();
        $props = $schema['properties']['platform']['properties'];

        self::assertArrayHasKey('name', $props);
        self::assertArrayHasKey('version', $props);
        self::assertArrayHasKey('security', $props);
        self::assertArrayHasKey('doctrine', $props);
        self::assertArrayHasKey('models', $props);

        self::assertSame('string', $props['name']['type']);
        self::assertSame('SolidWorx Platform', $props['name']['default']);
        self::assertSame('boolean', $props['security']['properties']['two_factor']['properties']['enabled']['type']);
        self::assertSame(User::class, $props['models']['properties']['user']['default']);
    }

    public function testWithRealUiConfiguration(): void
    {
        $generator = new SchemaGenerator([new UiConfiguration()]);
        $schema = $generator->generate();
        $ui = $schema['properties']['ui'];

        self::assertSame('object', $ui['type']);
        self::assertSame('UI / presentation configuration', $ui['description']);
        self::assertArrayHasKey('icon_pack', $ui['properties']);
        self::assertSame('tabler', $ui['properties']['icon_pack']['default']);
        self::assertArrayHasKey('templates', $ui['properties']);
    }

    public function testWithRealPlatformAndUiConfigurations(): void
    {
        $generator = new SchemaGenerator([
            new PlatformConfiguration(),
            new UiConfiguration(),
        ]);
        $schema = $generator->generate();
        $platformProps = $schema['properties']['platform']['properties'];

        // Root section keys (from PlatformConfiguration, key='')
        self::assertArrayHasKey('name', $platformProps);
        self::assertArrayHasKey('security', $platformProps);

        // Non-empty section keys are root-level siblings
        self::assertArrayNotHasKey('ui', $platformProps);
        self::assertArrayHasKey('ui', $schema['properties']);
    }

    /**
     * Build a minimal PlatformConfigurationInterface from a closure that configures the root node.
     *
     * @param Closure(ArrayNodeDefinition): void $setup
     */
    private function makeConfig(string $key, Closure $setup): PlatformConfigurationInterface
    {
        return new class($key, $setup) implements PlatformConfigurationInterface {
            /**
             * @param Closure(ArrayNodeDefinition): void $setup
             */
            public function __construct(
                private readonly string $key,
                private readonly Closure $setup,
            ) {
            }

            #[Override]
            public function getConfigSectionKey(): string
            {
                return $this->key;
            }

            #[Override]
            public function getTreeBuilder(): TreeBuilder
            {
                $rootName = $this->key !== '' ? $this->key : 'platform';
                $treeBuilder = new TreeBuilder($rootName);
                ($this->setup)($treeBuilder->getRootNode());
                return $treeBuilder;
            }
        };
    }
}
