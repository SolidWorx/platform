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

namespace SolidWorx\Platform\PlatformBundle\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[AsTaggedItem('controller.service_arguments')]
final class Login extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'solidworx_platform_ui.template.login')]
        private readonly string $loginTemplate
    ) {
    }

    public function __invoke(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $options = $request->attributes->get('_form_login_options');

        return $this->render($this->loginTemplate, [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'options' => $options,
        ]);
    }
}
