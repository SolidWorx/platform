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
 * Implemented by any Bundle class that owns a section in `platform.yaml`.
 *
 * Bundles returning an empty key own the `platform:` block itself. Bundles returning
 * a non-empty key (e.g. 'saas', 'ui') own a root-level sibling block of the same name.
 *
 * The Kernel discovers these implementations during bundle initialisation and injects
 * the relevant raw config sub-array so that each bundle's DI Extension can perform
 * its own validation using a private TreeBuilder.
 */
interface PlatformConfigSectionInterface extends PlatformConfigKeyInterface
{
    /**
     * Called by the Kernel after the raw config file is parsed.
     *
     * @param array<string, mixed> $rawConfig The raw (unvalidated) config sub-array for this bundle's section.
     */
    public function setPlatformRawConfig(array $rawConfig): void;
}
