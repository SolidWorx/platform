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

use Doctrine\ORM\EntityManagerInterface;
use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use SolidWorx\Platform\PlatformBundle\Enum\Flash;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\EmailVerifier;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

/**
 * The page the signed link in the verification email points to.
 *
 * It works without a session — the link is usually opened from a mail client — and signs the user
 * in on success, through the `form_login` authenticator of `platform.security.email_verification.firewall`.
 */
final class VerifyEmail extends BaseController
{
    public const string PATH = '/verify';

    public const string ROUTE_NAME = '_solidworx_platform_verify_email';

    public function __construct(
        private readonly EmailVerifier $emailVerifier,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'solidworx_platform.security.email_verification.firewall')]
        private readonly string $firewall,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->query->getString('id');
        $user = Ulid::isValid($id, Ulid::FORMAT_BASE_58) ? $this->entityManager->find(UserInterface::class, Ulid::fromString($id)) : null;

        if (! $user instanceof UserInterface) {
            $this->addFlash(Flash::Error->value, 'The email verification link is invalid.');

            return $this->redirectToLogin();
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $verifyEmailException) {
            $this->addFlash(Flash::Error->value, $this->translator->trans($verifyEmailException->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToLogin();
        }

        $this->addFlash(Flash::Success->value, 'Your email address has been verified.');

        return $this->security->login($user, 'form_login', $this->firewall) ?? $this->redirect('/');
    }

    private function redirectToLogin(): RedirectResponse
    {
        return $this->redirectToRoute('_login_' . $this->firewall);
    }
}
