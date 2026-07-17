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

namespace SolidWorx\Platform\Tests\Bundle\Ui\Component;

use PHPUnit\Framework\Attributes\CoversNothing;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use function preg_match;
use function substr_count;

/**
 * Renders the shared {@see Ui:Alert} anonymous Twig component and asserts its rendered markup, which
 * is a public contract consumed by downstream apps (e.g. SolidInvoice).
 */
#[CoversNothing]
final class AlertComponentTest extends KernelTestCase
{
    use InteractsWithTwigComponents;
    use MatchesSnapshots;

    protected function tearDown(): void
    {
        parent::tearDown();

        // Booting the kernel in debug mode registers a Symfony exception handler that it does not
        // remove; restore it so PHPUnit does not flag the test as risky.
        restore_exception_handler();
    }

    /**
     * Bug 1 regression: a consumer-passed `class` must be merged into the single cva-generated
     * `class` attribute — never emitted as a second `class` attribute that HTML normalization
     * would keep in place of (and thus discard) the `alert alert-info` styling.
     */
    public function testPassedClassIsMergedIntoTheSingleCvaClassAttribute(): void
    {
        $html = (string) $this->renderTwigComponent('Ui:Alert', [
            'type' => 'info',
            'class' => 'mb-4',
            'data-testid' => 'error-alert',
        ], 'Body');

        $rootTag = $this->rootOpeningTag($html);

        self::assertSame(1, substr_count($rootTag, 'class="'), 'The root element must carry exactly one class attribute.');
        self::assertStringContainsString('class="alert alert-info mb-4"', $rootTag);
        // Suppressing `class` on `{{ attributes }}` must not drop the consumer's other attributes.
        self::assertStringContainsString('data-testid="error-alert"', $rootTag);
    }

    /**
     * Bug 2 regression: the body wrapper must emit the muted class as a real `class` attribute,
     * not the bare boolean attribute `<div text-body-secondary>` that never applied any styling.
     */
    public function testNonImportantAlertMutesBodyText(): void
    {
        $html = (string) $this->renderTwigComponent('Ui:Alert', [
            'type' => 'info',
        ], 'Body');

        self::assertStringContainsString('<div class="text-body-secondary">', $html);
        self::assertStringNotContainsString('<div text-body-secondary>', $html);
    }

    public function testImportantAlertDoesNotMuteBodyText(): void
    {
        $html = (string) $this->renderTwigComponent('Ui:Alert', [
            'type' => 'primary',
            'important' => true,
        ], 'Body');

        self::assertStringContainsString('<div class="">', $html);
        self::assertStringNotContainsString('text-body-secondary', $html);
        self::assertStringContainsString('class="alert alert-primary alert-important"', $this->rootOpeningTag($html));
    }

    public function testTypeAndModifierVariantsAreReflectedInTheRootClasses(): void
    {
        $html = (string) $this->renderTwigComponent(
            'Ui:Alert',
            [
                'type' => 'danger',
                'dismissible' => true,
            ],
            'Body',
        );

        self::assertStringContainsString('class="alert alert-danger alert-dismissible"', $this->rootOpeningTag($html));
        self::assertStringContainsString('data-bs-dismiss="alert"', $html);
    }

    public function testLinkTitleAndAvatarBranchesRenderUnchanged(): void
    {
        $html = (string) $this->renderTwigComponent(
            'Ui:Alert',
            [
                'type' => 'warning',
                'title' => 'Heads up',
                'link' => '/notifications',
                'avatar' => '/img/avatar.png',
            ],
            'Body',
        );

        $rootTag = $this->rootOpeningTag($html);

        self::assertStringStartsWith('<a', $rootTag);
        self::assertStringContainsString('href="/notifications"', $rootTag);
        self::assertStringContainsString('class="alert alert-warning alert-link"', $rootTag);
        self::assertStringContainsString('<h4 class="alert-title">Heads up</h4>', $html);
        self::assertStringContainsString('background-image: url(/img/avatar.png)', $html);
    }

    public function testRenderedMarkupMatchesSnapshot(): void
    {
        $html = (string) $this->renderTwigComponent(
            'Ui:Alert',
            [
                'type' => 'success',
                'dismissible' => true,
                'title' => 'Saved',
                'class' => 'shadow-sm mb-4',
            ],
            'Your changes were saved.',
        );

        $this->assertMatchesHtmlSnapshot($html);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new AlertComponentTestKernel('test', true);
    }

    /**
     * Extracts the component's root opening tag (`<div …>` or `<a …>`) so assertions can target the
     * root element's raw attributes without being confused by the nested markup.
     */
    private function rootOpeningTag(string $html): string
    {
        self::assertSame(1, preg_match('/<(?:a|div)\b[^>]*>/', $html, $matches));

        return $matches[0];
    }
}
