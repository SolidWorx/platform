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

use Doctrine\Persistence\ManagerRegistry;
use Override;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use function array_first;

abstract class BaseController extends AbstractController
{
    #[Override]
    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    #[Override]
    protected function getUser(): ?User
    {
        $user = parent::getUser();

        if ($user instanceof User) {
            return $user;
        }

        return null;
    }

    protected function getUserId(): mixed
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            /** @var ManagerRegistry $registry */
            $registry = $this->container->get('doctrine');

            return array_first(
                (array) $registry
                ->getManagerForClass($user::class)
                ?->getClassMetadata($user::class)
                ->getIdentifierValues($user)
            );
        }

        return null;
    }

    public static function getSubscribedServices(): array
    {
        return parent::getSubscribedServices() + [
            'doctrine' => ManagerRegistry::class,
        ];
    }
}
