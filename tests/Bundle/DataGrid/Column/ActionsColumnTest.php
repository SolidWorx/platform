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
}
