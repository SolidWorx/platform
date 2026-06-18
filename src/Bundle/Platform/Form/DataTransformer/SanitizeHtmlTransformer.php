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

use Override;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use function is_string;

/**
 * Sanitizes submitted rich text HTML against a configured allow-list.
 *
 * @implements DataTransformerInterface<string, string>
 */
final readonly class SanitizeHtmlTransformer implements DataTransformerInterface
{
    public function __construct(
        private HtmlSanitizerInterface $sanitizer,
    ) {
    }

    #[Override]
    public function transform(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        return $value;
    }

    #[Override]
    public function reverseTransform(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        $sanitized = trim($this->sanitizer->sanitize($value));

        return $sanitized === '' ? null : $sanitized;
    }
}
