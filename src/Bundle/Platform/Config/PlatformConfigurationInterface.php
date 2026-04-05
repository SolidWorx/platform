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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Implemented by any service that contributes a section under `platform:` in the unified config.
 *
 * Implementations are auto-tagged as `solidworx_platform.configuration` and collected by
 * {@see SchemaGenerator} to build the JSON Schema for `platform.yaml` autocompletion.
 */
#[AutoconfigureTag('solidworx_platform.configuration')]
interface PlatformConfigurationInterface extends PlatformConfigKeyInterface
{
    /**
     * Build and return the TreeBuilder for this configuration section.
     * The root node name must match {@see getConfigSectionKey()} (or 'platform' for the root section).
     */
    public function getTreeBuilder(): TreeBuilder;
}
