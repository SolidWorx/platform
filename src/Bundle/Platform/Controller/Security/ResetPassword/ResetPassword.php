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

namespace SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword;

use Doctrine\ORM\EntityManagerInterface;
use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;
use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use SolidWorx\Platform\PlatformBundle\Enum\Flash;
use SolidWorx\Platform\PlatformBundle\Form\Type\Security\ResetPasswordType;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicyInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Webmozart\Assert\Assert;
use function is_string;

/**
 * The page the emailed link opens, where the user picks a new password.
 *
 * The token is moved from the URL into the session on the first hit, so it does not leak through
 * the Referer header or the browser history. Once the password is changed the token is spent, and
 * the user signs in again — through the login form, so two-factor authentication still applies.
 */
#[WithoutTenant]
final class ResetPassword extends BaseController
{
    public const string PATH = '/reset-password/reset/{token}';

    public const string ROUTE_NAME = '_solidworx_platform_reset_password';

    private const string SESSION_TOKEN = '_solidworx_platform_reset_password_public_token';

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordPolicyInterface $passwordPolicy,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'solidworx_platform.security.reset_password.templates.reset')]
        private readonly string $template,
        #[Autowire(param: 'solidworx_platform.security.reset_password.firewall')]
        private readonly string $firewall,
    ) {
    }

    public function __invoke(Request $request, ?string $token = null): Response
    {
        $session = $request->getSession();

        if ($token !== null) {
            $session->set(self::SESSION_TOKEN, $token);

            return $this->redirectToRoute(self::ROUTE_NAME);
        }

        $token = $session->get(self::SESSION_TOKEN);

        if (! is_string($token)) {
            return $this->restart('The password reset link is invalid. Please request a new one.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $resetPasswordException) {
            $session->remove(self::SESSION_TOKEN);

            return $this->restart($this->translator->trans($resetPasswordException->getReason(), [], 'ResetPasswordBundle'));
        }

        Assert::isInstanceOf($user, UserInterface::class);

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get(ResetPasswordType::NEW_PASSWORD)->getData();
            Assert::stringNotEmpty($newPassword);

            $this->resetPasswordHelper->removeResetRequest($token);

            $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

            // Opening the link proved the user reads mail at this address.
            if (! $user->isVerified()) {
                $user->setVerified(verified: true);
            }

            $this->entityManager->flush();

            $session->remove(self::SESSION_TOKEN);
            $session->remove(RequestResetPassword::SESSION_TOKEN);

            return new RedirectResponse($this->generateUrl('_login_' . $this->firewall))
                ->withFlash(Flash::Success, 'Your password has been reset. You can now sign in with your new password.');
        }

        return $this->render(
            $this->template,
            [
                'form' => $form,
                'password_requirements' => $this->passwordPolicy->requirements(),
                'login_route' => '_login_' . $this->firewall,
            ],
            new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK),
        );
    }

    private function restart(string $message): RedirectResponse
    {
        return new RedirectResponse($this->generateUrl(RequestResetPassword::ROUTE_NAME))
            ->withFlash(Flash::Error, $message);
    }
}
