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
 * Shared base for any class that owns a named section in `platform.yaml`.
 *
 * Both {@see PlatformConfigurationInterface} (schema-generation services) and
 * {@see PlatformConfigSectionInterface} (bundle classes that receive raw config) implement
 * this interface so that the section key is a single, consistent contract.
 */
interface PlatformConfigKeyInterface
{
    /**
     * The config section key this class covers.
     *
     * Return an empty string to own the root `platform:` block.
     * Return a non-empty string (e.g. 'saas', 'ui') to own a root-level sibling block.
     */
    public function getConfigSectionKey(): string;
}
