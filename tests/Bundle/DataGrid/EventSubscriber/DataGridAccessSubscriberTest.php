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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\EventSubscriber;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use LogicException;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Column\DoctrineColumnFactory;
use SolidWorx\Platform\DataGridBundle\EventSubscriber\DataGridAccessSubscriber;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridDefaults;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ClientDataGrid;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ExpressionSecuredDataGrid;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\SecuredDataGrid;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use function sprintf;

/**
 * {@see AjaxDataTableRegistry} and {@see AjaxDataTableTokenManager} are both
 * `final` upstream, so they cannot be stubbed or mocked. Every test instead
 * builds a real registry over a real token manager and a real
 * {@see ServiceLocator}, and derives tokens through the registry's own
 * getToken(), the same path the subscriber depends on.
 */
#[CoversClass(DataGridAccessSubscriber::class)]
final class DataGridAccessSubscriberTest extends TestCase
{
    private const string SECRET = 'test-secret';

    public function testUnrelatedRoutesAreUntouched(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $this->subscriber($checker)->onKernelRequest($this->event('app_dashboard'));
    }

    public function testUnauthenticatedRequestIsDenied(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, false],
        ]);

        $this->expectException(AccessDeniedException::class);

        $this->subscriber($checker)->onKernelRequest($this->event('ux_datatables_ajax_data'));
    }

    public function testAuthenticatedRequestForAGridWithoutItsOwnAttributeIsAllowed(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
        ]);

        $grid = new ClientDataGrid();

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_data', query: [
                'table' => $this->token($grid),
            ]));

        $this->expectNotToPerformAssertions();
    }

    public function testGridAttributeIsEnforced(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
            ['ROLE_ADMIN', null, false],
        ]);

        $grid = new SecuredDataGrid();

        $this->expectException(AccessDeniedException::class);

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_data', query: [
                'table' => $this->token($grid),
            ]));
    }

    public function testGridAttributeGrantedIsAllowed(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);

        $grid = new SecuredDataGrid();

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_data', query: [
                'table' => $this->token($grid),
            ]));

        $this->expectNotToPerformAssertions();
    }

    public function testExportRouteIsAlsoCheckedPerGrid(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
            ['ROLE_ADMIN', null, false],
        ]);

        $grid = new SecuredDataGrid();

        $this->expectException(AccessDeniedException::class);

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_export', query: [
                'table' => $this->token($grid),
            ]));
    }

    /**
     * Regression test for the fail-open hole found in round 1 of review:
     * ux_datatables_ajax_templates carries the identical read token to
     * data/export, but in the request body under the same "table" key
     * (AjaxTemplateRenderController::__invoke() calls
     * $request->getPayload()->getString('table')), not the query string. A
     * subscriber that only ever looked at the query string -- or only ever
     * checked the two routes it happened to know about -- left this route
     * completely unguarded despite it being a read oracle for any grid.
     */
    public function testTemplatesRouteIsAlsoCheckedPerGrid(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
            ['ROLE_ADMIN', null, false],
        ]);

        $grid = new SecuredDataGrid();

        $this->expectException(AccessDeniedException::class);

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_templates', body: [
                'table' => $this->token($grid),
            ]));
    }

    /**
     * A mutation route (delete, here) resolves the grid through
     * resolveAction() rather than get(), and reads the token from the
     * request body under "dataTable" rather than "table" -- proving the
     * per-grid check also covers the five action-token routes, not just the
     * three read-token ones.
     */
    public function testMutationRouteIsAlsoCheckedPerGrid(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
            ['ROLE_ADMIN', null, false],
        ]);

        // Unlike the read-token routes, resolveAction() calls
        // getEntityClass(), which fully initialises the grid -- so, unlike
        // every other test here, this fixture needs real DataGridDefaults.
        $grid = $this->withDataGridDefaults(new SecuredDataGrid());

        $this->expectException(AccessDeniedException::class);

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_delete', body: [
                'dataTable' => $this->actionToken($grid),
            ]));
    }

    /**
     * getSecurityAttribute() may return an Expression rather than a role
     * string; SecuredDataGrid only ever exercises the string branch, so this
     * covers the other one explicitly instead of leaving it to inspection.
     */
    public function testExpressionAttributeIsEnforced(): void
    {
        // willReturnMap() matches arguments by value, and the Expression this
        // grid returns is never the same instance passed here, so it can
        // never match a map entry -- willReturnCallback() inspects the
        // argument directly instead, which works for any object.
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => $attribute === 'IS_AUTHENTICATED_FULLY',
        );

        $grid = new ExpressionSecuredDataGrid();

        $this->expectException(AccessDeniedException::class);

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_data', query: [
                'table' => $this->token($grid),
            ]));
    }

    /**
     * A garbage/tampered action token must not become a 500
     * (InvalidDataTableTokenException escaping uncaught) and must not be
     * treated as a bypass either: there is no grid to check an attribute
     * against, so the request reaches the controller, which derives the
     * same token via resolveAction() and rejects it itself.
     */
    public function testForgedActionTokenDoesNotCrashOrBypassTheCheck(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
        ]);

        $this->subscriber($checker)->onKernelRequest(
            $this->event('ux_datatables_ajax_delete', body: [
                'dataTable' => 'not-a-real-signed-token',
            ]),
        );

        $this->expectNotToPerformAssertions();
    }

    public function testSubRequestsAreIgnored(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $this->subscriber($checker)->onKernelRequest(
            $this->event('ux_datatables_ajax_data', type: HttpKernelInterface::SUB_REQUEST),
        );
    }

    private function subscriber(
        AuthorizationCheckerInterface $checker,
        ?AbstractDataTable $grid = null,
    ): DataGridAccessSubscriber {
        return new DataGridAccessSubscriber($checker, $this->registry($grid), 'IS_AUTHENTICATED_FULLY');
    }

    /**
     * Builds a real registry, keyed on the given grid's class when one is
     * given. The HMAC signature depends only on the fixed secret and the
     * class name, so a registry built here and one built by token()/
     * actionToken() for the same grid instance produce the same token even
     * though they are different objects.
     */
    private function registry(?AbstractDataTable $grid = null): AjaxDataTableRegistry
    {
        $tokenManager = new AjaxDataTableTokenManager(self::SECRET);

        if (! $grid instanceof AbstractDataTable) {
            return new AjaxDataTableRegistry(new ServiceLocator([]), $tokenManager, []);
        }

        $serviceId = 'test.data_grid';

        return new AjaxDataTableRegistry(
            new ServiceLocator([
                $serviceId => static fn (): AbstractDataTable => $grid,
            ]),
            $tokenManager,
            [
                $grid::class => $serviceId,
            ],
        );
    }

    private function token(AbstractDataTable $grid): string
    {
        return $this->registry($grid)->getToken($grid::class)
            ?? throw new LogicException(sprintf('No read token could be generated for "%s".', $grid::class));
    }

    private function actionToken(AbstractDataTable $grid): string
    {
        return $this->registry($grid)->getActionToken($grid::class)
            ?? throw new LogicException(sprintf('No action token could be generated for "%s".', $grid::class));
    }

    /**
     * Mirrors AbstractDataGridTest::createGrid(): DoctrineColumnFactory is
     * `final readonly`, so it cannot be mocked -- build a real one over an
     * in-memory SQLite EntityManager instead.
     */
    private function withDataGridDefaults(AbstractDataGrid $grid): AbstractDataGrid
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        $grid->setDataGridDefaults(new DataGridDefaults(
            columnFactory: new DoctrineColumnFactory(new EntityManager($connection, $config)),
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            responsive: true,
            columnControl: true,
            tableClass: 'table table-vcenter card-table',
            exportEnabled: true,
            exportFormats: ['csv', 'xlsx'],
        ));

        return $grid;
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $body
     */
    private function event(
        string $route,
        array $query = [],
        array $body = [],
        int $type = HttpKernelInterface::MAIN_REQUEST,
    ): RequestEvent {
        $request = new Request($query, $body);
        $request->attributes->set('_route', $route);

        return new RequestEvent(self::createStub(KernelInterface::class), $request, $type);
    }
}
