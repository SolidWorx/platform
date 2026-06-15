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

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Builds an opinionated {@see HtmlSanitizer} tailored to a set of allowed elements.
 *
 * Sanitization is the security boundary for the rich text editor: whatever the browser submits is
 * filtered down to this allow-list on the server, so a tampered or malicious payload can never persist
 * scripts, event handlers or unsafe URL schemes.
 */
final class HtmlSanitizerFactory
{
    /**
     * @param list<string> $allowedElements
     */
    public function create(array $allowedElements): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        foreach ($allowedElements as $element) {
            $config = $config->allowElement($element, $element === 'a' ? ['href'] : []);
        }

        return new HtmlSanitizer($config);
    }
}
