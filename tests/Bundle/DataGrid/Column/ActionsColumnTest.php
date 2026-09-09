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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Column\ActionsColumn;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

#[CoversClass(ActionsColumn::class)]
final class ActionsColumnTest extends TestCase
{
    public function testDefaultsToTheActionsNameAndTemplate(): void
    {
        $column = ActionsColumn::new();

        self::assertSame('actions', $column->getName());
        self::assertSame('@DataGrid/columns/actions.html.twig', $column->getTemplate());
    }

    public function testActionsAreCollectedInDeclarationOrder(): void
    {
        $column = ActionsColumn::new()
            ->link(route: 'app_client_show', icon: 'tabler:eye', label: 'View')
            ->edit()
            ->delete(confirm: 'Delete this client?');

        self::assertSame(
            [
                [
                    'type' => 'CUSTOM',
                    'icon' => 'tabler:eye',
                    'label' => 'View',
                    'route' => 'app_client_show',
                    'routeParameter' => 'id',
                    'confirm' => null,
                ],
                [
                    'type' => 'EDIT',
                    'icon' => 'tabler:pencil',
                    'label' => 'Edit',
                    'route' => null,
                    'routeParameter' => null,
                    'confirm' => null,
                ],
                [
                    'type' => 'DELETE',
                    'icon' => 'tabler:trash',
                    'label' => 'Delete',
                    'route' => null,
                    'routeParameter' => null,
                    'confirm' => 'Delete this client?',
                ],
            ],
            $column->getTemplateParameters()['actions'],
        );
    }

    public function testIdentifierFieldIsPassedToTheTemplate(): void
    {
        $column = ActionsColumn::new()->identifiedBy('uuid');

        self::assertSame('uuid', $column->getTemplateParameters()['idField']);
    }

    public function testIdentifierFieldDefaultsToId(): void
    {
        self::assertSame('id', ActionsColumn::new()->getTemplateParameters()['idField']);
    }

    /**
     * A test guarding only the state left by the LAST mutator in a chain would
     * still pass even if an earlier mutator (e.g. an action one) forgot to
     * re-send the template parameters, because the later `identifiedBy()`
     * call re-sends whatever `$this->actions` already holds. Chaining both
     * here and asserting on both keys catches that regardless of which
     * mutator ran first.
     */
    public function testAnActionMutatorAndIdentifiedByBothSurviveWhenChained(): void
    {
        $column = ActionsColumn::new()
            ->edit()
            ->identifiedBy('uuid');

        $parameters = $column->getTemplateParameters();

        self::assertSame('uuid', $parameters['idField']);
        self::assertSame(
            [
                [
                    'type' => 'EDIT',
                    'icon' => 'tabler:pencil',
                    'label' => 'Edit',
                    'route' => null,
                    'routeParameter' => null,
                    'confirm' => null,
                ],
            ],
            $parameters['actions'],
        );
    }

    /**
     * Upstream's ActionRowDataResolver::resolveActionUrl() catches
     * RoutingExceptionInterface around the identical path() call and treats a
     * null URL as "omit the action". A row with no idField key (or one where
     * identifiedBy() names a field the row does not carry) resolves `id` to
     * null; the CUSTOM link must be skipped rather than calling path() with
     * it, and the row's other actions must still render.
     */
    public function testRowWithoutTheIdentifierFieldRendersWithoutThrowingOrABrokenLink(): void
    {
        $column = ActionsColumn::new()
            ->link(route: 'app_client_show', icon: 'tabler:eye', label: 'View')
            ->edit()
            ->delete();

        $html = $this->renderActionsTemplate($column, [
            'name' => 'Acme',
        ]);

        self::assertStringNotContainsString('data-action-type="CUSTOM"', $html);
        self::assertStringNotContainsString('<a ', $html);
        self::assertStringContainsString('data-action-type="EDIT"', $html);
        self::assertStringContainsString('data-action-type="DELETE"', $html);
    }

    /**
     * Renders the real `actions.html.twig` shipped by the bundle, with a
     * `path()` implementation that fails the way Symfony's real
     * UrlGenerator does when handed a null route parameter — so this only
     * passes if the template itself avoids calling path() with a null id,
     * not because the stub happens to tolerate it.
     *
     * @param array<string, mixed> $row
     */
    private function renderActionsTemplate(ActionsColumn $column, array $row): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../../src/Bundle/DataGrid/templates', 'DataGrid');

        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $name, array $parameters = []): string {
                foreach ($parameters as $value) {
                    if ($value === null) {
                        throw new InvalidParameterException('A required route parameter is null.');
                    }
                }

                return '/' . $name;
            },
        );

        $twig = new Environment($loader);
        $twig->addExtension(new RoutingExtension($urlGenerator));
        $twig->addFunction(new TwigFunction(
            'ux_icon',
            static fn (string $name, array $attributes = []): string => '<svg data-icon="' . $name . '"></svg>',
            [
                'is_safe' => ['html'],
            ],
        ));

        return $twig->render($column->getTemplate(), [
            ...$column->getTemplateParameters(),
            'row' => $row,
        ]);
    }
}
