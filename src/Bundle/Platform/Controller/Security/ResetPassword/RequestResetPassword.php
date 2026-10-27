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

use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;
use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use SolidWorx\Platform\PlatformBundle\Form\Type\Security\ResetPasswordRequestType;
use SolidWorx\Platform\PlatformBundle\Security\EmailLinkContext;
use SolidWorx\Platform\PlatformBundle\Security\ResetPassword\ResetPasswordMailer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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
        private readonly ResetPasswordMailer $resetPasswordMailer,
        private readonly EmailLinkContext $linkContext,
        #[Autowire(param: 'solidworx_platform.security.reset_password.templates.request')]
        private readonly string $template,
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

            $token = $this->resetPasswordMailer->send($email) ?? $this->resetPasswordHelper->generateFakeResetToken();
            $token->clearToken();

            $request->getSession()->set(self::SESSION_TOKEN, $token);

            return $this->redirectToRoute(CheckResetPasswordEmail::ROUTE_NAME);
        }

        return $this->renderPage($form, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
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
