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

namespace SolidWorx\Platform\UiBundle\Exception;

use InvalidArgumentException;
use SolidWorx\Platform\UiBundle\Layout\LayoutOption;
use function array_map;
use function get_debug_type;
use function implode;
use function is_scalar;
use function levenshtein;
use function sprintf;
use function strlen;
use function var_export;

/**
 * Thrown when a template sets a layout option that does not exist, or gives it a value the option
 * does not accept.
 *
 * A silently ignored `{% set layout = {navbar_stick: true} %}` is the kind of typo that costs an
 * afternoon, so the layouts fail loudly instead.
 */
final class InvalidLayoutOptionException extends InvalidArgumentException
{
    public static function unknownOption(string $name): self
    {
        $message = sprintf('Unknown layout option "%s".', $name);

        $suggestion = self::closestMatch($name);

        $message .= $suggestion !== null
            ? sprintf(' Did you mean "%s"?', $suggestion)
            : sprintf(' Available options are: %s.', implode(', ', LayoutOption::names()));

        return new self($message);
    }

    public static function invalidValue(LayoutOption $option, mixed $value): self
    {
        $allowed = $option->allowedValues();

        $expected = $allowed !== null
            ? implode(', ', array_map(
                static fn (?string $allowedValue): string => $allowedValue === null ? 'null' : '"' . $allowedValue . '"',
                $allowed,
            ))
            : $option->type();

        return new self(sprintf(
            'Layout option "%s" does not accept %s. Expected %s.',
            $option->value,
            is_scalar($value) || $value === null ? var_export($value, true) : get_debug_type($value),
            $expected,
        ));
    }

    private static function closestMatch(string $name): ?string
    {
        $closest = null;
        $shortestDistance = null;

        foreach (LayoutOption::names() as $candidate) {
            $distance = levenshtein($name, $candidate);

            if ($shortestDistance === null || $distance < $shortestDistance) {
                $closest = $candidate;
                $shortestDistance = $distance;
            }
        }

        // Only suggest when the typo is plausibly a typo, rather than an unrelated word.
        if ($shortestDistance === null || $shortestDistance > (int) (strlen($name) / 2)) {
            return null;
        }

        return $closest;
    }
}
