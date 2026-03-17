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

namespace SolidWorx\Platform\PlatformBundle\Config;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\FloatNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\VariableNode;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

/**
 * Generates a JSON Schema array from the platform's registered {@see PlatformConfigurationInterface} sections.
 *
 * Each bundle contributes its own configuration section by implementing {@see PlatformConfigurationInterface}.
 * This generator collects those sections and converts their Symfony Config {@see NodeInterface} trees into a
 * single JSON Schema document suitable for IDE autocompletion of `platform.yaml`.
 */
final readonly class SchemaGenerator
{
    /**
     * @param iterable<PlatformConfigurationInterface> $configurations
     */
    public function __construct(
        #[AutowireIterator('solidworx_platform.configuration')]
        private iterable $configurations,
    ) {
    }

    /**
     * Build and return the full JSON Schema array for `platform.yaml`.
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $platformProperties = [];

        foreach ($this->configurations as $configuration) {
            $tree = $configuration->getTreeBuilder()->buildTree();
            $key = $configuration->getConfigSectionKey();

            if ($key === '') {
                // Root section: expose each child as a direct property of `platform:`
                if ($tree instanceof ArrayNode) {
                    foreach ($tree->getChildren() as $name => $child) {
                        $platformProperties[$name] = $this->nodeToSchema($child);
                    }
                }
            } else {
                $platformProperties[$key] = $this->nodeToSchema($tree);
            }
        }

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'title' => 'SolidWorx Platform Configuration',
            'description' => 'Configuration schema for platform.yaml',
            'type' => 'object',
            'properties' => [
                'platform' => [
                    'type' => 'object',
                    'description' => 'Platform configuration',
                    'properties' => $platformProperties,
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => true,
        ];
    }

    /**
     * Convert any Symfony Config node to its JSON Schema representation.
     *
     * @return array<string, mixed>
     */
    private function nodeToSchema(NodeInterface $node): array
    {
        $schema = [];

        if ($node instanceof BaseNode) {
            $info = $node->getInfo();
            if ($info !== null && $info !== '') {
                $schema['description'] = $info;
            }
        }

        if (! $node->isRequired() && $node->hasDefaultValue()) {
            try {
                $schema['default'] = $node->getDefaultValue();
            } catch (Throwable) {
                // getDefaultValue() can throw for prototype nodes with unresolved defaults — safe to skip
            }
        }

        $typeSchema = match (true) {
            $node instanceof PrototypedArrayNode => $this->prototypedArrayToSchema($node),
            $node instanceof ArrayNode => $this->arrayNodeToSchema($node),
            $node instanceof BooleanNode => [
                'type' => 'boolean',
            ],
            $node instanceof EnumNode => $this->enumNodeToSchema($node),
            $node instanceof IntegerNode => [
                'type' => 'integer',
            ],
            $node instanceof FloatNode => [
                'type' => 'number',
            ],
            $node instanceof VariableNode => [], // no type restriction — accepts any value
            default => $this->scalarNodeToSchema($node),
        };

        return array_merge($schema, $typeSchema);
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayNodeToSchema(ArrayNode $node): array
    {
        $properties = [];
        $required = [];

        foreach ($node->getChildren() as $name => $child) {
            $properties[$name] = $this->nodeToSchema($child);
            if ($child->isRequired()) {
                $required[] = $name;
            }
        }

        $schema = [
            'type' => 'object',
        ];

        if ($properties !== []) {
            $schema['properties'] = $properties;
        }

        if ($required !== []) {
            $schema['required'] = $required;
        }

        $schema['additionalProperties'] = false;

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function prototypedArrayToSchema(PrototypedArrayNode $node): array
    {
        $prototypeSchema = $this->nodeToSchema($node->getPrototype());

        // useAttributeAsKey() makes this an object with dynamic string keys
        if ($node->getKeyAttribute() !== null) {
            return [
                'type' => 'object',
                'additionalProperties' => $prototypeSchema,
            ];
        }

        return [
            'type' => 'array',
            'items' => $prototypeSchema,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enumNodeToSchema(EnumNode $node): array
    {
        return [
            'type' => 'string',
            'enum' => array_values($node->getValues()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scalarNodeToSchema(NodeInterface $node): array
    {
        // Detect nullable scalar nodes (those with a null default, e.g. ->defaultNull())
        if ($node->hasDefaultValue()) {
            try {
                if ($node->getDefaultValue() === null) {
                    return [
                        'type' => ['string', 'null'],
                    ];
                }
            } catch (Throwable) {
                // ignore
            }
        }

        return [
            'type' => 'string',
        ];
    }
}
