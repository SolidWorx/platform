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

use LogicException;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\EventSubscriber\DataGridAccessSubscriber;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ClientDataGrid;
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

        $this->expectNotToPerformAssertions();
    }

    public function testUnauthenticatedRequestIsDenied(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, false],
        ]);

        $this->expectException(AccessDeniedException::class);

        $this->subscriber($checker)->onKernelRequest($this->event('ux_datatables_ajax_data'));
    }

    public function testAuthenticatedRequestForAGridWithoutItsOwnAttributeIsAllowed(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
        ]);

        $grid = new ClientDataGrid();

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_data', $this->token($grid)));

        $this->expectNotToPerformAssertions();
    }

    public function testGridAttributeIsEnforced(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
            ['ROLE_ADMIN', null, false],
        ]);

        $grid = new SecuredDataGrid();

        $this->expectException(AccessDeniedException::class);

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_data', $this->token($grid)));
    }

    public function testGridAttributeGrantedIsAllowed(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);

        $grid = new SecuredDataGrid();

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_data', $this->token($grid)));

        $this->expectNotToPerformAssertions();
    }

    public function testExportRouteIsAlsoCheckedPerGrid(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['IS_AUTHENTICATED_FULLY', null, true],
            ['ROLE_ADMIN', null, false],
        ]);

        $grid = new SecuredDataGrid();

        $this->expectException(AccessDeniedException::class);

        $this->subscriber($checker, $grid)
            ->onKernelRequest($this->event('ux_datatables_ajax_export', $this->token($grid)));
    }

    public function testSubRequestsAreIgnored(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $this->subscriber($checker)->onKernelRequest(
            $this->event('ux_datatables_ajax_data', type: HttpKernelInterface::SUB_REQUEST),
        );

        $this->expectNotToPerformAssertions();
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
     * class name, so a registry built here and one built by token() for the
     * same grid instance produce the same token even though they are
     * different objects.
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
            ?? throw new LogicException(sprintf('No token could be generated for "%s".', $grid::class));
    }

    private function event(
        string $route,
        string $token = '',
        int $type = HttpKernelInterface::MAIN_REQUEST,
    ): RequestEvent {
        $request = new Request($token === '' ? [] : [
            'table' => $token,
        ]);
        $request->attributes->set('_route', $route);

        return new RequestEvent(self::createStub(KernelInterface::class), $request, $type);
    }
}
