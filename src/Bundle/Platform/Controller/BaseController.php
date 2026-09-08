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

namespace SolidWorx\Platform\PlatformBundle\Controller;

use Override;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class BaseController extends AbstractController
{
    #[Override]
    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    #[Override]
    protected function getUser(): ?UserInterface
    {
        $user = parent::getUser();

        if ($user instanceof UserInterface) {
            return $user;
        }

        return null;
    }

    /**
     * The signed-in user, for pages that cannot render without one.
     *
     * `#[IsGranted]` already keeps anonymous visitors out, so reaching this with no user means
     * the configured `platform.models.user` class does not implement the platform contract. That
     * is a misconfiguration rather than a request to answer — refusing is safer than rendering a
     * page about nobody.
     */
    protected function currentUser(): UserInterface
    {
        $user = $this->getUser();

        if (! $user instanceof UserInterface) {
            throw $this->createAccessDeniedException('The authenticated user does not implement the platform user contract.');
        }

        return $user;
    }
}
