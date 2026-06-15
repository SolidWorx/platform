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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Form\TextEditor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Form\TextEditor\ToolbarPreset;

#[CoversClass(ToolbarPreset::class)]
final class ToolbarPresetTest extends TestCase
{
    public function testMinimalPresetFeatures(): void
    {
        self::assertSame(['bold', 'italic', 'link'], ToolbarPreset::Minimal->features());
    }

    public function testAllowedTagsTrackEnabledFeatures(): void
    {
        $tags = ToolbarPreset::Minimal->allowedTags();

        self::assertContains('strong', $tags);
        self::assertContains('em', $tags);
        self::assertContains('a', $tags);
        self::assertContains('p', $tags);
        // Headings are not part of the minimal preset.
        self::assertNotContains('h1', $tags);
        self::assertNotContains('h2', $tags);
    }

    public function testFullPresetAllowsHeadingsAndCodeBlocks(): void
    {
        $tags = ToolbarPreset::Full->allowedTags();

        self::assertContains('h1', $tags);
        self::assertContains('pre', $tags);
        self::assertContains('hr', $tags);
    }

    public function testAllowedTagsAreUnique(): void
    {
        $tags = ToolbarPreset::Full->allowedTags();

        self::assertSame(array_values(array_unique($tags)), $tags);
    }

    public function testAllowedNodesAlwaysIncludeTheBaseDocument(): void
    {
        $nodes = ToolbarPreset::Minimal->allowedNodes();

        self::assertContains('doc', $nodes);
        self::assertContains('paragraph', $nodes);
        self::assertContains('text', $nodes);
    }

    public function testAllowedMarksTrackEnabledFeatures(): void
    {
        self::assertSame(['bold', 'italic', 'link'], ToolbarPreset::Minimal->allowedMarks());
    }

    public function testAllowedHeadingLevels(): void
    {
        self::assertSame([], ToolbarPreset::Minimal->allowedHeadingLevels());
        self::assertSame([2, 3], ToolbarPreset::Default->allowedHeadingLevels());
        self::assertSame([1, 2, 3], ToolbarPreset::Full->allowedHeadingLevels());
    }
}
