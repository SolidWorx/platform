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
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
     * The routes carrying the read token in the query string, where the grid
     * can be resolved cheaply. The mutation routes carry an action token in the
     * body and are already gated by CSRF plus that token.
     */
    private const array TOKENED_ROUTES = [
        'ux_datatables_ajax_data',
        'ux_datatables_ajax_export',
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

        if (! in_array($route, self::TOKENED_ROUTES, true)) {
            return;
        }

        $token = $request->query->getString('table');

        if ($token === '') {
            return;
        }

        $grid = $this->registry->get($token);

        if (! $grid instanceof AbstractDataGrid) {
            return;
        }

        $attribute = $grid->getSecurityAttribute();

        if ($attribute === null || $this->authorizationChecker->isGranted($attribute)) {
            return;
        }

        // The table token identifies which grid is requested, not who is
        // asking, so an authenticated user holding another grid's token would
        // otherwise read it.
        throw new AccessDeniedException(sprintf(
            'Access to the data grid "%s" is denied.',
            $grid::class,
        ));
    }
}
