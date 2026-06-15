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

namespace SolidWorx\Platform\PlatformBundle\Form\TextEditor;

/**
 * Opinionated toolbar presets for the {@see \SolidWorx\Platform\PlatformBundle\Form\Type\TextEditorType}.
 *
 * Each preset maps to an ordered list of editor features. The same preset drives both the client-side
 * toolbar (which buttons to render) and the server-side sanitization allow-lists (which HTML tags and
 * ProseMirror nodes/marks are permitted), so the two can never drift apart.
 */
enum ToolbarPreset: string
{
    case Minimal = 'minimal';
    case Default = 'default';
    case Full = 'full';

    /**
     * Maps an editor feature to the HTML tags it may produce.
     *
     * @var array<string, list<string>>
     */
    private const array FEATURE_TAGS = [
        'bold' => ['strong'],
        'italic' => ['em'],
        'strike' => ['s'],
        'heading1' => ['h1'],
        'heading2' => ['h2'],
        'heading3' => ['h3'],
        'bulletList' => ['ul', 'li'],
        'orderedList' => ['ol', 'li'],
        'blockquote' => ['blockquote'],
        'code' => ['code'],
        'codeBlock' => ['pre', 'code'],
        'horizontalRule' => ['hr'],
        'link' => ['a'],
        'undo' => [],
        'redo' => [],
    ];

    /**
     * Maps an editor feature to the ProseMirror node types it may produce.
     *
     * @var array<string, list<string>>
     */
    private const array FEATURE_NODES = [
        'heading1' => ['heading'],
        'heading2' => ['heading'],
        'heading3' => ['heading'],
        'bulletList' => ['bulletList', 'listItem'],
        'orderedList' => ['orderedList', 'listItem'],
        'blockquote' => ['blockquote'],
        'codeBlock' => ['codeBlock'],
        'horizontalRule' => ['horizontalRule'],
    ];

    /**
     * Maps an editor feature to the ProseMirror mark types it may produce.
     *
     * @var array<string, list<string>>
     */
    private const array FEATURE_MARKS = [
        'bold' => ['bold'],
        'italic' => ['italic'],
        'strike' => ['strike'],
        'code' => ['code'],
        'link' => ['link'],
    ];

    /**
     * The ordered list of toolbar features enabled for this preset.
     *
     * @return list<string>
     */
    public function features(): array
    {
        return match ($this) {
            self::Minimal => ['bold', 'italic', 'link'],
            self::Default => [
                'heading2', 'heading3',
                'bold', 'italic', 'strike',
                'bulletList', 'orderedList', 'blockquote', 'code',
                'link',
                'undo', 'redo',
            ],
            self::Full => [
                'heading1', 'heading2', 'heading3',
                'bold', 'italic', 'strike',
                'bulletList', 'orderedList', 'blockquote', 'code', 'codeBlock',
                'horizontalRule', 'link',
                'undo', 'redo',
            ],
        };
    }

    /**
     * The HTML tags permitted when storing the editor output as sanitized HTML.
     *
     * @return list<string>
     */
    public function allowedTags(): array
    {
        return $this->collect(self::FEATURE_TAGS, ['p', 'br']);
    }

    /**
     * The ProseMirror node types permitted when storing the editor output as JSON.
     *
     * @return list<string>
     */
    public function allowedNodes(): array
    {
        return $this->collect(self::FEATURE_NODES, ['doc', 'paragraph', 'text', 'hardBreak']);
    }

    /**
     * The ProseMirror mark types permitted when storing the editor output as JSON.
     *
     * @return list<string>
     */
    public function allowedMarks(): array
    {
        return $this->collect(self::FEATURE_MARKS, []);
    }

    /**
     * The heading levels permitted for this preset.
     *
     * @return list<int>
     */
    public function allowedHeadingLevels(): array
    {
        $levels = [];

        foreach ($this->features() as $feature) {
            if (preg_match('/^heading([1-6])$/', $feature, $matches) === 1) {
                $levels[] = (int) $matches[1];
            }
        }

        return $levels;
    }

    /**
     * @param array<string, list<string>> $map
     * @param list<string>                 $base
     *
     * @return list<string>
     */
    private function collect(array $map, array $base): array
    {
        $values = $base;

        foreach ($this->features() as $feature) {
            foreach ($map[$feature] ?? [] as $value) {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }
}
