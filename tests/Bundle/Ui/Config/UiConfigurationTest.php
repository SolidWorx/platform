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
     * @return array{icon_pack: string, templates: array{base: string, login: string}}
     */
    private function process(array $config): array
    {
        /** @var array{icon_pack: string, templates: array{base: string, login: string}} */
        return $this->processor->process($this->configuration->getTreeBuilder()->buildTree(), [$config]);
    }
}
