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

namespace SolidWorx\Platform\Tests\Bundle\Ui\Config;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\UiBundle\Config\UiConfiguration;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @phpstan-import-type UiConfig from UiConfiguration
 */
#[CoversClass(UiConfiguration::class)]
final class UiConfigurationTest extends TestCase
{
    private UiConfiguration $configuration;

    private Processor $processor;

    #[Override]
    protected function setUp(): void
    {
        $this->configuration = new UiConfiguration();
        $this->processor = new Processor();
    }

    public function testGetConfigSectionKeyReturnsUi(): void
    {
        self::assertSame('ui', $this->configuration->getConfigSectionKey());
    }

    public function testTreeBuilderRootNodeIsNamedUi(): void
    {
        $tree = $this->configuration->getTreeBuilder()->buildTree();
        self::assertSame('ui', $tree->getName());
    }

    public function testTreeBuilderRootNodeIsArrayNode(): void
    {
        $tree = $this->configuration->getTreeBuilder()->buildTree();
        self::assertInstanceOf(ArrayNode::class, $tree);
    }

    public function testGetTreeBuilderReturnsFreshInstanceEachCall(): void
    {
        self::assertNotSame(
            $this->configuration->getTreeBuilder(),
            $this->configuration->getTreeBuilder(),
        );
    }

    public function testDefaultIconPackIsTabler(): void
    {
        $result = $this->process([]);
        self::assertSame('tabler', $result['icon_pack']);
    }

    public function testDefaultBaseTemplateIsUiLayout(): void
    {
        $result = $this->process([]);
        self::assertSame('@Ui/Layout/base.html.twig', $result['templates']['base']);
    }

    public function testDefaultLoginTemplateIsUiSecurity(): void
    {
        $result = $this->process([]);
        self::assertSame('@Ui/Security/login.html.twig', $result['templates']['login']);
    }

    public function testDefaultLayoutTemplatesAreTheShippedOnes(): void
    {
        $result = $this->process([]);

        self::assertSame('@Ui/Layout/app.html.twig', $result['templates']['layouts']['app']);
        self::assertSame('@Ui/Layout/condensed.html.twig', $result['templates']['layouts']['condensed']);
        self::assertSame('@Ui/Layout/clean.html.twig', $result['templates']['layouts']['clean']);
    }

    public function testCustomLayoutTemplateIsApplied(): void
    {
        $result = $this->process([
            'templates' => [
                'layouts' => [
                    'app' => '@App/layout/app.html.twig',
                ],
            ],
        ]);

        self::assertSame('@App/layout/app.html.twig', $result['templates']['layouts']['app']);
        // Untouched layouts keep the shipped default.
        self::assertSame('@Ui/Layout/clean.html.twig', $result['templates']['layouts']['clean']);
    }

    public function testUnknownLayoutTemplateKeysAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([
            'templates' => [
                'layouts' => [
                    'print' => '@App/layout/print.html.twig',
                ],
            ],
        ]);
    }

    public function testLayoutDefaultsMatchTheTablerDefaults(): void
    {
        $result = $this->process([]);

        self::assertSame([
            'theme' => null,
            'fluid' => false,
            'boxed' => false,
            'navbar' => true,
            'navbar_theme' => null,
            'navbar_sticky' => false,
            'navbar_overlap' => false,
            'navbar_expand' => 'md',
            'sidebar' => true,
            'sidebar_theme' => 'dark',
            'sidebar_position' => 'start',
            'sidebar_transparent' => false,
            'sidebar_expand' => 'lg',
            'page_header' => true,
            'footer' => true,
        ], $result['layout']);
    }

    public function testLayoutDefaultsCanBeOverridden(): void
    {
        $result = $this->process([
            'layout' => [
                'navbar_theme' => 'dark',
                'navbar_sticky' => true,
                'fluid' => true,
            ],
        ]);

        self::assertSame('dark', $result['layout']['navbar_theme']);
        self::assertTrue($result['layout']['navbar_sticky']);
        self::assertTrue($result['layout']['fluid']);
        // Options that were not set keep their default.
        self::assertSame('dark', $result['layout']['sidebar_theme']);
    }

    public function testInvalidLayoutEnumValueIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([
            'layout' => [
                'navbar_theme' => 'purple',
            ],
        ]);
    }

    public function testUnknownLayoutOptionsAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([
            'layout' => [
                'rounded' => true,
            ],
        ]);
    }

    public function testCustomIconPackIsApplied(): void
    {
        $result = $this->process([
            'icon_pack' => 'fontawesome',
        ]);
        self::assertSame('fontawesome', $result['icon_pack']);
    }

    public function testCustomBaseTemplateIsApplied(): void
    {
        $result = $this->process([
            'templates' => [
                'base' => '@App/layout/base.html.twig',
            ],
        ]);
        self::assertSame('@App/layout/base.html.twig', $result['templates']['base']);
    }

    public function testCustomLoginTemplateIsApplied(): void
    {
        $result = $this->process([
            'templates' => [
                'login' => '@App/security/login.html.twig',
            ],
        ]);
        self::assertSame('@App/security/login.html.twig', $result['templates']['login']);
    }

    public function testUnknownKeysAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([
            'unknown_key' => 'value',
        ]);
    }

    public function testUnknownTemplateKeysAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([
            'templates' => [
                'custom' => '@App/layout.html.twig',
            ],
        ]);
    }

    public function testFullConfigIsProcessed(): void
    {
        $result = $this->process([
            'icon_pack' => 'heroicons',
            'templates' => [
                'base' => '@App/layout/app.html.twig',
                'login' => '@App/auth/login.html.twig',
            ],
        ]);

        self::assertSame('heroicons', $result['icon_pack']);
        self::assertSame('@App/layout/app.html.twig', $result['templates']['base']);
        self::assertSame('@App/auth/login.html.twig', $result['templates']['login']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return UiConfig
     */
    private function process(array $config): array
    {
        /** @var UiConfig */
        return $this->processor->process($this->configuration->getTreeBuilder()->buildTree(), [$config]);
    }
}
