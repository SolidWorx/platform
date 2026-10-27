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

namespace SolidWorx\Platform\PlatformBundle\Security\ResetPassword;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\ResetPassword;
use SolidWorx\Platform\PlatformBundle\Exception\UntrustedLinkHostException;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Security\EmailLinkContext;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Mails a password reset link, the same email the "forgot password" form sends, so non-form
 * callers (an API, a console command) can send it too.
 *
 * It never says whether an account exists: an unknown or disabled address, a link sent too
 * recently and a mail failure all return null. Rate limiting by client is left to the caller.
 */
final readonly class ResetPasswordMailer
{
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private EmailLinkContext $linkContext,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'solidworx_platform.security.reset_password.email.template')]
        private string $emailTemplate,
        #[Autowire(param: 'solidworx_platform.security.reset_password.email.subject')]
        private string $emailSubject,
    ) {
    }

    /**
     * @return ResetPasswordToken|null the token mailed, or null when nothing was sent
     *
     * @throws UntrustedLinkHostException
     */
    public function send(string $email): ?ResetPasswordToken
    {
        // Refused up front, for every address alike, so a misconfiguration says nothing about
        // which addresses have an account.
        $this->linkContext->assertRequestHostIsTrusted();

        $user = $this->entityManager->getRepository(UserInterface::class)->findOneBy([
            'email' => $email,
            'enabled' => true,
        ]);

        if (! $user instanceof UserInterface) {
            return null;
        }

        try {
            $token = $this->resetPasswordHelper->generateResetToken($user);
        } catch (TooManyPasswordRequestsException) {
            // A link was sent within `throttle_limit` and is still valid; that one will do.
            return null;
        }

        $resetUrl = $this->linkContext->build(fn (): string => $this->urlGenerator->generate(ResetPassword::ROUTE_NAME, [
            'token' => $token->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));

        try {
            $this->mailer->send(
                new TemplatedEmail()
                    ->to($email)
                    ->subject($this->translator->trans($this->emailSubject))
                    ->htmlTemplate($this->emailTemplate)
                    ->context([
                        'user' => $user,
                        'resetUrl' => $resetUrl,
                        'expiresAtMessageKey' => $token->getExpirationMessageKey(),
                        'expiresAtMessageData' => $token->getExpirationMessageData(),
                    ]),
            );
        } catch (Exception $exception) {
            // Any failure — a mail server that is down, a missing sender address — ends the same
            // way as an unknown address; an error here would tell the caller the account exists.
            // The request is dropped, so the throttle does not make the user wait for an email
            // that never left.
            $this->resetPasswordHelper->removeResetRequest($token->getToken());
            $this->logger->error('The password reset email could not be sent.', [
                'exception' => $exception,
            ]);

            return null;
        }

        return $token;
    }
}
