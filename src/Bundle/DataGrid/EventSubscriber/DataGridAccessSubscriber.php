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

namespace SolidWorx\Platform\DataGridBundle\EventSubscriber;

use Override;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Exception\InvalidDataTableTokenException;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use function in_array;
use function sprintf;

/**
 * Guards the Ajax routes the DataTables bundle registers.
 *
 * Not a prepended `security.access_control` rule: that list is first-match-wins
 * and ordered, so inserting an entry into an application's rules would silently
 * change the meaning of every rule after it.
 *
 * Two layers: every route requires the configured `ajaxAccess` attribute, and
 * every route on which a grid can be resolved additionally requires that
 * grid's own {@see AbstractDataGrid::getSecurityAttribute()}. The table token
 * embedded in rendered HTML identifies which grid is requested, not who is
 * asking, so the second check applies uniformly to every route a token can be
 * pulled from -- there is no safe way to carve out a subset by token type or
 * CSRF status, because CSRF only proves the request came from our page, never
 * that this user may read or mutate this particular grid.
 */
final readonly class DataGridAccessSubscriber implements EventSubscriberInterface
{
    /**
     * Every route registered by Pentiminax\UX\DataTables\Routing\RouteLoader.
     */
    private const array ROUTES = [
        'ux_datatables_ajax_data',
        'ux_datatables_ajax_templates',
        'ux_datatables_ajax_edit',
        'ux_datatables_ajax_delete',
        'ux_datatables_ajax_edit_form',
        'ux_datatables_ajax_edit_form_submit',
        'ux_datatables_ajax_detail',
        'ux_datatables_ajax_export',
    ];

    /**
     * Routes carrying the unprivileged read token, resolved through
     * AjaxDataTableRegistry::get() -- the same call each controller makes.
     * `data` and `export` carry it in the query string; `templates` carries
     * the identical token under the same "table" key, but in the request
     * body (see AjaxTemplateRenderController::__invoke()).
     */
    private const array READ_TOKEN_ROUTES = [
        'ux_datatables_ajax_data',
        'ux_datatables_ajax_export',
        'ux_datatables_ajax_templates',
    ];

    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        #[Autowire(service: 'datatables.ajax.registry')]
        private AjaxDataTableRegistry $registry,
        #[Autowire(param: 'solidworx_platform_datagrid.security.ajax_access')]
        private string $ajaxAccess,
    ) {
    }

    /**
     * @return array<string, array{string, int}>
     */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        // After the firewall (priority 8) has established a token, so
        // IS_AUTHENTICATED_FULLY reflects the real request.
        return [
            RequestEvent::class => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');

        if (! in_array($route, self::ROUTES, true)) {
            return;
        }

        if (! $this->authorizationChecker->isGranted($this->ajaxAccess)) {
            throw new AccessDeniedException(sprintf(
                'Access to the data grid endpoint "%s" requires "%s".',
                $route,
                $this->ajaxAccess,
            ));
        }

        $table = $this->resolveTable($route, $request);

        // Not our AbstractDataGrid: either no token resolved to a table at
        // all -- the controller re-derives the identical token itself and
        // rejects the request on its own -- or it resolved to a raw upstream
        // AbstractDataTable, which never had the platform's security hook
        // and so has nothing further to enforce.
        if (! $table instanceof AbstractDataGrid) {
            return;
        }

        $attribute = $table->getSecurityAttribute();

        if ($attribute === null || $this->authorizationChecker->isGranted($attribute)) {
            return;
        }

        // The table token identifies which grid is requested, not who is
        // asking, so an authenticated user holding another grid's token would
        // otherwise read or mutate it.
        throw new AccessDeniedException(sprintf(
            'Access to the data grid "%s" is denied.',
            $table::class,
        ));
    }

    private function resolveTable(string $route, Request $request): ?AbstractDataTable
    {
        if (in_array($route, self::READ_TOKEN_ROUTES, true)) {
            $token = $route === 'ux_datatables_ajax_templates'
                ? $request->getPayload()->getString('table')
                : $request->query->getString('table');

            return $token === '' ? null : $this->registry->get($token);
        }

        // The remaining five routes (edit, delete, edit_form,
        // edit_form_submit, detail) all carry a signed action token under
        // "dataTable" in the request body -- see AjaxEntityQueryDto,
        // AjaxEditRequestDto and AjaxEditFormRequestDto upstream.
        $token = $request->getPayload()->getString('dataTable');

        if ($token === '') {
            return null;
        }

        try {
            // resolveAction() calls AbstractDataTable::getEntityClass(),
            // which fully initialises the grid (runs configureDataTable() /
            // configureColumns()). Harmless here: grids are container
            // services with DataGridDefaults already injected, and the
            // controller would initialise the same instance moments later
            // regardless.
            return $this->registry->resolveAction($token)->table;
        } catch (InvalidDataTableTokenException) {
            // An unresolvable action token must not crash this listener (a
            // 500) and must not be treated as "no grid to check" in a way
            // that silently allows anything through: there genuinely is no
            // grid here, so the request continues to the controller, which
            // calls resolveAction() on the same token and rejects it itself
            // with the identical InvalidDataTableTokenException.
            return null;
        }
    }
}
