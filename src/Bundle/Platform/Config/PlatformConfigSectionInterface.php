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

/**
 * Implemented by any Bundle class that contributes a section under the `platform:` config root.
 *
 * The Kernel discovers these implementations during bundle initialisation and injects
 * the relevant raw config sub-array (keyed by {@see getConfigSectionKey()}) so that
 * each bundle's DI Extension can perform its own validation using a private TreeBuilder.
 */
interface PlatformConfigSectionInterface
{
    /**
     * Returns the key under `platform:` that belongs to this bundle.
     *
     * Use an empty string to indicate the bundle owns the root-level `platform:` section
     * (i.e. name, version, security, doctrine, models).
     *
     * Examples: '', 'saas', 'ui'
     */
    public function getConfigSectionKey(): string;

    /**
     * Called by the Kernel after the raw config file is parsed.
     *
     * @param array<string, mixed> $rawConfig The raw (unvalidated) config sub-array for this bundle's section.
     */
    public function setPlatformRawConfig(array $rawConfig): void;
}
