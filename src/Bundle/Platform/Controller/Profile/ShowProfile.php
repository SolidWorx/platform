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

namespace SolidWorx\Platform\PlatformBundle\Controller\Profile;

use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The profile landing page: what we hold about the signed-in user, and the two ways to change it.
 *
 * The route takes no user identifier — there is nothing in the URL, the query string or the body
 * to point it at somebody else's account. The subject is always {@see self::getUser()}, which
 * resolves from the session token alone. Every page under `/profile` works the same way, which is
 * what makes "can user A edit user B" unanswerable rather than merely guarded.
 *
 * `IS_AUTHENTICATED_FULLY` (rather than `IS_AUTHENTICATED_REMEMBERED`) means a remember-me cookie
 * on its own does not open the page: viewing an email address and a phone number, next to the
 * buttons that change them, asks for a real sign-in.
 */
#[Route(path: self::PATH, name: self::ROUTE_NAME, methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ShowProfile extends BaseController
{
    /**
     * The path of the profile page.
     */
    public const string PATH = '/profile';

    /**
     * The route name of the profile page, linked to from the user menu.
     */
    public const string ROUTE_NAME = 'solidworx_platform_profile_show';

    public function __construct(
        #[Autowire(param: 'solidworx_platform.profile.templates.show')]
        private readonly string $template,
        #[Autowire(param: 'solidworx_platform.security.two_factor.enabled')]
        private readonly bool $twoFactorEnabled,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render($this->template, [
            'user' => $this->currentUser(),
            'two_factor_enabled' => $this->twoFactorEnabled,
        ]);
    }
}
