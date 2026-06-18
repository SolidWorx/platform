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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Form\Type;

use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidWorx\Platform\PlatformBundle\Form\DataTransformer\JsonDocumentTransformer;
use SolidWorx\Platform\PlatformBundle\Form\DataTransformer\SanitizeHtmlTransformer;
use SolidWorx\Platform\PlatformBundle\Form\TextEditor\HtmlSanitizerFactory;
use SolidWorx\Platform\PlatformBundle\Form\TextEditor\ToolbarPreset;
use SolidWorx\Platform\PlatformBundle\Form\Type\TextEditorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

#[CoversClass(TextEditorType::class)]
#[CoversClass(SanitizeHtmlTransformer::class)]
#[CoversClass(JsonDocumentTransformer::class)]
#[CoversClass(HtmlSanitizerFactory::class)]
#[CoversClass(ToolbarPreset::class)]
#[AllowMockObjectsWithoutExpectations]
final class TextEditorTypeTest extends TypeTestCase
{
    public function testItIsBuiltOnTopOfATextarea(): void
    {
        $view = $this->factory->create(TextEditorType::class)->createView();
        $blockPrefixes = $view->vars['block_prefixes'];

        self::assertIsArray($blockPrefixes);
        self::assertContains('textarea', $blockPrefixes);
        self::assertContains('text_editor', $blockPrefixes);
    }

    public function testItExposesToolbarConfigurationToTheView(): void
    {
        $view = $this->factory->create(TextEditorType::class, null, [
            'toolbar' => 'minimal',
            'placeholder' => 'Start typing…',
            'editor_height' => '20rem',
        ])->createView();

        self::assertSame('html', $view->vars['output_format']);
        self::assertSame(['bold', 'italic', 'link'], $view->vars['toolbar']);
        self::assertSame('Start typing…', $view->vars['editor_placeholder']);
        self::assertSame('20rem', $view->vars['editor_height']);
    }

    public function testItKeepsAllowedFormattingHtml(): void
    {
        $form = $this->factory->create(TextEditorType::class);
        $form->submit('<p>Hello <strong>bold</strong> and <em>italic</em></p>');

        self::assertTrue($form->isSynchronized());
        self::assertIsString($form->getData());
        self::assertStringContainsString('<strong>bold</strong>', $form->getData());
        self::assertStringContainsString('<em>italic</em>', $form->getData());
    }

    public function testItStripsDangerousHtmlOnTheServer(): void
    {
        $form = $this->factory->create(TextEditorType::class);
        $form->submit('<p>safe</p><script>alert(1)</script><img src=x onerror="alert(1)">');

        self::assertTrue($form->isSynchronized());
        self::assertIsString($form->getData());
        self::assertStringContainsString('safe', $form->getData());
        self::assertStringNotContainsString('<script', $form->getData());
        self::assertStringNotContainsString('onerror', $form->getData());
        self::assertStringNotContainsString('<img', $form->getData());
    }

    public function testItStripsUnsafeLinkSchemes(): void
    {
        $form = $this->factory->create(TextEditorType::class);
        $form->submit('<p><a href="javascript:alert(1)">click</a></p>');

        self::assertTrue($form->isSynchronized());
        self::assertIsString($form->getData());
        self::assertStringContainsString('click', $form->getData());
        self::assertStringNotContainsString('javascript', $form->getData());
    }

    public function testEmptyHtmlBecomesNull(): void
    {
        $form = $this->factory->create(TextEditorType::class);
        $form->submit('');

        self::assertTrue($form->isSynchronized());
        self::assertNull($form->getData());
    }

    public function testJsonOutputIsStoredAsStringByDefault(): void
    {
        $document = $this->paragraph('Hello world');

        $form = $this->factory->create(TextEditorType::class, null, [
            'output_format' => 'json',
        ]);
        $form->submit((string) json_encode($document));

        self::assertTrue($form->isSynchronized());
        self::assertIsString($form->getData());
        self::assertSame($document, json_decode($form->getData(), true));
    }

    public function testJsonOutputCanBeDecodedToAnArray(): void
    {
        $document = $this->paragraph('Hello world');

        $form = $this->factory->create(TextEditorType::class, null, [
            'output_format' => 'json',
            'json_as_array' => true,
        ]);
        $form->submit((string) json_encode($document));

        self::assertTrue($form->isSynchronized());
        self::assertSame($document, $form->getData());
    }

    public function testJsonRejectsDisallowedNodeTypes(): void
    {
        $form = $this->factory->create(TextEditorType::class, null, [
            'output_format' => 'json',
        ]);
        $form->submit((string) json_encode([
            'type' => 'doc',
            'content' => [[
                'type' => 'evilNode',
            ]],
        ]));

        self::assertFalse($form->isSynchronized());
    }

    public function testJsonRejectsMalformedDocuments(): void
    {
        $form = $this->factory->create(TextEditorType::class, null, [
            'output_format' => 'json',
        ]);
        $form->submit('{not valid json');

        self::assertFalse($form->isSynchronized());
    }

    public function testInvalidOutputFormatIsRejected(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(TextEditorType::class, null, [
            'output_format' => 'pdf',
        ]);
    }

    public function testInvalidToolbarPresetIsRejected(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(TextEditorType::class, null, [
            'toolbar' => 'fancy',
        ]);
    }

    public function testParentIsTextarea(): void
    {
        self::assertSame(TextareaType::class, (new TextEditorType(new HtmlSanitizerFactory()))->getParent());
    }

    #[Override]
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([
                new TextEditorType(new HtmlSanitizerFactory()),
            ], []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paragraph(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $text,
                        ],
                    ],
                ],
            ],
        ];
    }
}
