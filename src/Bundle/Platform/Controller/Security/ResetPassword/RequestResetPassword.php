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
use Exception;
use Psr\Log\LoggerInterface;
use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;
use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use SolidWorx\Platform\PlatformBundle\Form\Type\Security\ResetPasswordRequestType;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Security\EmailLinkContext;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Webmozart\Assert\Assert;

/**
 * The "forgot password" page: asks for an email address and mails a reset link to it.
 *
 * Whatever happens — no such account, a disabled one, a link sent too recently, a mail server
 * that is down — the visitor lands on the same "check your email" page, so the form cannot be
 * used to find out which addresses have an account. Only the per-IP rate limit is reported, as
 * it says nothing about any account.
 */
#[WithoutTenant]
final class RequestResetPassword extends BaseController
{
    public const string PATH = '/reset-password';

    public const string ROUTE_NAME = '_solidworx_platform_reset_password_request';

    /**
     * The token of the last request, with its secret part cleared, so the "check your email" page
     * can say how long the link is valid for.
     */
    public const string SESSION_TOKEN = '_solidworx_platform_reset_password_token';

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly EmailLinkContext $linkContext,
        #[Autowire(param: 'solidworx_platform.security.reset_password.templates.request')]
        private readonly string $template,
        #[Autowire(param: 'solidworx_platform.security.reset_password.email.template')]
        private readonly string $emailTemplate,
        #[Autowire(param: 'solidworx_platform.security.reset_password.email.subject')]
        private readonly string $emailSubject,
        #[Autowire(param: 'solidworx_platform.security.reset_password.firewall')]
        private readonly string $firewall,
        // Set by the extension while `rate_limit.enabled` is true.
        private readonly ?RateLimiterFactoryInterface $limiter = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // Refused up front, for every visitor alike, so a misconfiguration says nothing about
        // which addresses have an account.
        $this->linkContext->assertRequestHostIsTrusted();

        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->limiter instanceof RateLimiterFactoryInterface && ! $this->limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                $form->addError(new FormError('Too many password reset requests. Please try again later.'));

                return $this->renderPage($form, Response::HTTP_TOO_MANY_REQUESTS);
            }

            $email = $form->get(ResetPasswordRequestType::EMAIL)->getData();
            Assert::string($email);

            $token = $this->sendResetEmail($email) ?? $this->resetPasswordHelper->generateFakeResetToken();
            $token->clearToken();

            $request->getSession()->set(self::SESSION_TOKEN, $token);

            return $this->redirectToRoute(CheckResetPasswordEmail::ROUTE_NAME);
        }

        return $this->renderPage($form, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * @return ResetPasswordToken|null the token mailed, or null when nothing was sent
     */
    private function sendResetEmail(string $email): ?ResetPasswordToken
    {
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

        $resetUrl = $this->linkContext->build(fn (): string => $this->generateUrl(ResetPassword::ROUTE_NAME, [
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
            // way as an unknown address; an error page here would tell the visitor the account
            // exists. The request is dropped, so the throttle does not make the user wait for an
            // email that never left.
            $this->resetPasswordHelper->removeResetRequest($token->getToken());
            $this->logger->error('The password reset email could not be sent.', [
                'exception' => $exception,
            ]);

            return null;
        }

        return $token;
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function renderPage(FormInterface $form, int $status): Response
    {
        return $this->render($this->template, [
            'form' => $form,
            'login_route' => '_login_' . $this->firewall,
        ], new Response(status: $status));
    }
}
