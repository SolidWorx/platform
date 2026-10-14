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

namespace SolidWorx\Platform\PlatformBundle\Security\EmailVerification;

use Doctrine\ORM\EntityManagerInterface;
use SolidWorx\Platform\PlatformBundle\Controller\Security\VerifyEmail;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Sends the signed verification link, and marks the user verified when it comes back.
 *
 * The email is rendered from `platform.security.email_verification.email.template` with
 * `signedUrl`, `expiresAtMessageKey`, `expiresAtMessageData` and `user` in its context.
 */
final readonly class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        #[Autowire(param: 'solidworx_platform.security.email_verification.email.template')]
        private string $template,
        #[Autowire(param: 'solidworx_platform.security.email_verification.email.subject')]
        private string $subject,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendEmailConfirmation(UserInterface $user): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            VerifyEmail::ROUTE_NAME,
            $user->getId()->toString(),
            (string) $user->getEmail(),
            [
                'id' => $user->getId()->toBase58(),
            ],
        );

        $email = new TemplatedEmail()
            ->to((string) $user->getEmail())
            ->subject($this->translator->trans($this->subject))
            ->htmlTemplate($this->template)
            ->context([
                'user' => $user,
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAtMessageKey' => $signature->getExpirationMessageKey(),
                'expiresAtMessageData' => $signature->getExpirationMessageData(),
            ]);

        $this->mailer->send($email);
    }

    /**
     * @throws VerifyEmailExceptionInterface when the link is invalid, tampered with or expired
     */
    public function handleEmailConfirmation(Request $request, UserInterface $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, $user->getId()->toString(), (string) $user->getEmail());

        $user->setVerified(verified: true);

        $this->entityManager->flush();
    }
}
