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

namespace SolidWorx\Platform\UiBundle\Layout;

use SolidWorx\Platform\UiBundle\Exception\InvalidLayoutOptionException;
use function array_key_exists;
use function is_string;

/**
 * Merges the three layers a layout is built from, and rejects anything that is not a real option.
 *
 * Precedence, lowest first:
 *
 *   1. {@see LayoutOption::defaults()}
 *   2. `platform.ui.layout` — the application's house style
 *   3. the `layout` variable the template sets when it extends a layout
 */
final readonly class LayoutResolver
{
    /**
     * @param array<string, bool|string|null> $applicationDefaults The validated `platform.ui.layout` section.
     */
    public function __construct(
        private array $applicationDefaults = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $overrides
     *
     * @return array<string, bool|string|null>
     *
     * @throws InvalidLayoutOptionException
     */
    public function resolve(array $overrides = []): array
    {
        $resolved = LayoutOption::defaults();

        foreach ($this->applicationDefaults as $name => $value) {
            // Already validated by the Config component, but keep the merge honest if it was not.
            if (array_key_exists($name, $resolved)) {
                $resolved[$name] = $value;
            }
        }

        return [...$resolved, ...$this->validate($overrides)];
    }

    /**
     * Checks that every key is a real option and every value one the option accepts.
     *
     * @param array<array-key, mixed> $overrides
     *
     * @return array<string, bool|string|null>
     *
     * @throws InvalidLayoutOptionException
     */
    public function validate(array $overrides): array
    {
        $validated = [];

        foreach ($overrides as $name => $value) {
            if (! is_string($name)) {
                throw InvalidLayoutOptionException::unknownOption((string) $name);
            }

            $option = LayoutOption::tryFrom($name) ?? throw InvalidLayoutOptionException::unknownOption($name);

            if (! $option->accepts($value)) {
                throw InvalidLayoutOptionException::invalidValue($option, $value);
            }

            /** @var bool|string|null $value */
            $validated[$name] = $value;
        }

        return $validated;
    }
}
