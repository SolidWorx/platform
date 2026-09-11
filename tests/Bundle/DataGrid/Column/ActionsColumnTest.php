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
use ReflectionClass;
use SolidWorx\Platform\DataGridBundle\Column\ActionsColumn;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Entity\Client;
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
     * Guards edit() in isolation. A test that only asserts after a later
     * mutator has also run (e.g. identifiedBy()) would still pass even if
     * edit() itself forgot to re-send the template parameters, because
     * addAction() always pushes onto `$this->actions` regardless of whether
     * applyTemplate() was actually called, and the later mutator's own
     * applyTemplate() call would re-send that already-mutated array. Asserting
     * immediately after edit() alone, with nothing after it, closes that gap.
     */
    public function testEditAloneUpdatesTheActionsParameter(): void
    {
        $column = ActionsColumn::new()->edit();

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
            $column->getTemplateParameters()['actions'],
        );
    }

    /**
     * Guards delete() in isolation, for the same reason as
     * testEditAloneUpdatesTheActionsParameter() above.
     */
    public function testDeleteAloneUpdatesTheActionsParameter(): void
    {
        $column = ActionsColumn::new()->delete(confirm: 'Delete this client?');

        self::assertSame(
            [
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

    /**
     * Guards link() in isolation, for the same reason as
     * testEditAloneUpdatesTheActionsParameter() above.
     */
    public function testLinkAloneUpdatesTheActionsParameter(): void
    {
        $column = ActionsColumn::new()->link(route: 'app_client_show', icon: 'tabler:eye', label: 'View');

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
            ],
            $column->getTemplateParameters()['actions'],
        );
    }

    /**
     * A test guarding only the state left by the LAST mutator in a chain would
     * still pass even if an earlier mutator (e.g. an action one) forgot to
     * re-send the template parameters, because the later `identifiedBy()`
     * call re-sends whatever `$this->actions` already holds. Chaining both
     * here and asserting on both keys catches that regardless of which
     * mutator ran first. This does not replace the isolated tests above — it
     * additionally guards the interaction between an action mutator and
     * identifiedBy() together.
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
     * Upstream's TemplateColumnRenderer::renderRow() passes the SOURCE ENTITY as `row` and
     * the mapped array as `payload` (see RowProcessingPipeline::map(), which calls
     * renderRow(row: $mappedRow, mappedRow: $row) — the argument names are swapped relative
     * to the parameter names). A grid that does not drop the id column from
     * configureColumns() carries it in the mapped array, so the template must read `id` from
     * `payload`, not from `row` (which is the entity, not an array).
     */
    public function testActionsUseTheIdFromTheMappedPayloadRow(): void
    {
        $column = ActionsColumn::new()
            ->link(route: 'app_client_show', icon: 'tabler:eye', label: 'View')
            ->edit()
            ->delete();

        $html = $this->renderActionsTemplate($column, new Client(), [
            'id' => 42,
            'name' => 'Acme',
        ]);

        self::assertStringContainsString('data-id="42"', $html);
        self::assertStringContainsString('data-action-type="EDIT"', $html);
        self::assertStringContainsString('data-action-type="DELETE"', $html);
        self::assertStringContainsString('href="/app_client_show"', $html);
    }

    /**
     * A grid that overrides configureColumns() and drops the id column entirely never puts
     * it in the mapped `payload` array. The template must then fall back to reading the
     * field off the source entity itself (`row`), via a getter.
     */
    public function testActionsFallBackToTheEntityWhenThePayloadDropsTheIdentifierColumn(): void
    {
        $client = new Client();
        $idProperty = new ReflectionClass(Client::class)->getProperty('id');
        $idProperty->setValue($client, 7);

        $column = ActionsColumn::new()
            ->link(route: 'app_client_show', icon: 'tabler:eye', label: 'View')
            ->edit()
            ->delete();

        $html = $this->renderActionsTemplate($column, $client, [
            'name' => 'Acme',
        ]);

        self::assertStringContainsString('data-id="7"', $html);
        self::assertStringContainsString('href="/app_client_show"', $html);
    }

    /**
     * Upstream's ActionRowDataResolver::resolveActionUrl() catches
     * RoutingExceptionInterface around the identical path() call and treats a
     * null URL as "omit the action". A row where identifiedBy() names a field
     * that neither the mapped payload nor the source entity carries resolves
     * `id` to null; the CUSTOM link must be skipped rather than calling
     * path() with it, and the row's other actions must still render.
     */
    public function testRowWithoutTheIdentifierFieldRendersWithoutThrowingOrABrokenLink(): void
    {
        $column = ActionsColumn::new()
            ->link(route: 'app_client_show', icon: 'tabler:eye', label: 'View')
            ->edit()
            ->delete()
            ->identifiedBy('uuid');

        $html = $this->renderActionsTemplate($column, new Client(), [
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
     * Binds `row` to the source entity and `payload` to the mapped array, matching the
     * context upstream's TemplateColumnRenderer::renderRow() builds in production.
     *
     * @param array<string, mixed> $payload
     */
    private function renderActionsTemplate(ActionsColumn $column, object $row, array $payload): string
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
            'payload' => $payload,
        ]);
    }
}
