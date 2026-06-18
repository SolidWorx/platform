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

namespace SolidWorx\Platform\PlatformBundle\Form\DataTransformer;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use JsonException;
use Override;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Validates and sanitizes a Tiptap (ProseMirror) JSON document.
 *
 * The submitted document is rejected unless every node and mark type is part of the toolbar's allow-list,
 * and link marks are stripped of unsafe URL schemes. This keeps JSON output as secure as the HTML path.
 *
 * @implements DataTransformerInterface<array<array-key, mixed>|string, string>
 */
final readonly class JsonDocumentTransformer implements DataTransformerInterface
{
    private const array SAFE_LINK_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * @param list<string> $allowedNodes
     * @param list<string> $allowedMarks
     * @param list<int>    $allowedHeadingLevels
     */
    public function __construct(
        private array $allowedNodes,
        private array $allowedMarks,
        private array $allowedHeadingLevels,
        private bool $asArray,
    ) {
    }

    #[Override]
    public function transform(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            try {
                return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $exception) {
                throw new TransformationFailedException('Unable to encode the document.', 0, $exception);
            }
        }

        throw new TransformationFailedException('Expected an array or JSON string.');
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    #[Override]
    public function reverseTransform(mixed $value): array | string | null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new TransformationFailedException('Expected a JSON string.');
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TransformationFailedException('Invalid JSON document.', 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new TransformationFailedException('Invalid document structure.');
        }

        $clean = $this->sanitizeNode($decoded);

        if ($this->asArray) {
            return $clean;
        }

        try {
            return json_encode($clean, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new TransformationFailedException('Unable to encode the document.', 0, $exception);
        }
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array<array-key, mixed>
     */
    private function sanitizeNode(array $node): array
    {
        $type = $node['type'] ?? null;

        if (! is_string($type) || ! in_array($type, $this->allowedNodes, true)) {
            throw new TransformationFailedException(sprintf('Disallowed node type "%s".', is_string($type) ? $type : get_debug_type($type)));
        }

        if ($type === 'heading') {
            $attrs = $node['attrs'] ?? null;
            $level = is_array($attrs) ? ($attrs['level'] ?? null) : null;

            if (! in_array($level, $this->allowedHeadingLevels, true)) {
                throw new TransformationFailedException('Disallowed heading level.');
            }
        }

        if (\array_key_exists('marks', $node)) {
            if (! is_array($node['marks'])) {
                throw new TransformationFailedException('Invalid marks.');
            }

            $node['marks'] = $this->sanitizeMarks($node['marks']);
        }

        if (\array_key_exists('content', $node)) {
            if (! is_array($node['content'])) {
                throw new TransformationFailedException('Invalid node content.');
            }

            $node['content'] = array_values(array_map(
                function (mixed $child): array {
                    if (! is_array($child)) {
                        throw new TransformationFailedException('Invalid node.');
                    }

                    return $this->sanitizeNode($child);
                },
                $node['content'],
            ));
        }

        return $node;
    }

    /**
     * @param array<array-key, mixed> $marks
     *
     * @return list<array<array-key, mixed>>
     */
    private function sanitizeMarks(array $marks): array
    {
        $clean = [];

        foreach ($marks as $mark) {
            if (! is_array($mark)) {
                throw new TransformationFailedException('Invalid mark.');
            }

            $type = $mark['type'] ?? null;

            if (! is_string($type) || ! in_array($type, $this->allowedMarks, true)) {
                throw new TransformationFailedException(sprintf('Disallowed mark type "%s".', is_string($type) ? $type : get_debug_type($type)));
            }

            if ($type === 'link') {
                $attrs = $mark['attrs'] ?? null;

                if (! is_array($attrs)) {
                    continue;
                }

                $href = $attrs['href'] ?? null;

                if (! is_string($href) || ! $this->isSafeUrl($href)) {
                    // Drop the unsafe link but keep the text content intact.
                    continue;
                }

                $attrs['rel'] = 'noopener noreferrer';
                $mark['attrs'] = $attrs;
            }

            $clean[] = $mark;
        }

        return $clean;
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if (str_starts_with($url, '//')) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            // Relative URLs (no scheme) are safe.
            return true;
        }

        return in_array(strtolower($scheme), self::SAFE_LINK_SCHEMES, true);
    }
}
