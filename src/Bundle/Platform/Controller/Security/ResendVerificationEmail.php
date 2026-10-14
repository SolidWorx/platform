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

use Carbon\Carbon;
use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;
use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use SolidWorx\Platform\PlatformBundle\Enum\Flash;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\EmailVerifier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use function is_int;
use function str_starts_with;

/**
 * Sends the signed-in user a fresh verification link.
 *
 * POST only, with its CSRF token checked in the action, so a link or an image on another site
 * cannot make us email the user. It is a form button on the banner and the profile page; render
 * one with `@SolidWorxPlatform/EmailVerification/_resend_form.html.twig`. Works outside a tenant,
 * since the banner shows on every page — including ones that stand in for a missing tenant or plan.
 */
#[IsGranted(attribute: 'IS_AUTHENTICATED')]
#[WithoutTenant]
final class ResendVerificationEmail extends BaseController
{
    public const string PATH = '/verify/resend';

    public const string ROUTE_NAME = '_solidworx_platform_verify_email_resend';

    public const string CSRF_TOKEN_ID = 'resend_verification_email';

    /**
     * Seconds a user has to wait between two links, so the button cannot be used to flood an inbox.
     */
    public const int COOLDOWN = 60;

    private const string SESSION_KEY = '_solidworx_platform_verification_email_sent_at';

    public function __construct(
        private readonly EmailVerifier $emailVerifier,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $session = $request->getSession();
        $lastSentAt = $session->get(self::SESSION_KEY);

        // Checked here rather than with #[IsCsrfTokenValid]: Symfony treats that failure as an
        // authentication error and sends a signed-in user to the login page.
        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $this->addFlash(Flash::Error->value, 'Your session has expired. Please try again.');
        } elseif ($user->isVerified()) {
            $this->addFlash(Flash::Info->value, 'Your email address is already verified.');
        } elseif (is_int($lastSentAt) && Carbon::now()->getTimestamp() - $lastSentAt < self::COOLDOWN) {
            $this->addFlash(Flash::Warning->value, 'A verification email was sent recently. Please wait a minute before requesting another one.');
        } else {
            try {
                $this->emailVerifier->sendEmailConfirmation($user);
                $session->set(self::SESSION_KEY, Carbon::now()->getTimestamp());
                $this->addFlash(Flash::Success->value, 'A new verification email has been sent. Please check your inbox.');
            } catch (TransportExceptionInterface) {
                $this->addFlash(Flash::Error->value, 'The verification email could not be sent. Please try again later.');
            }
        }

        return $this->redirect($this->backUrl($request));
    }

    /**
     * Back to the page the button was on — but only if it is on this site, so the Referer header
     * cannot turn this into an open redirect.
     */
    private function backUrl(Request $request): string
    {
        $referer = (string) $request->headers->get('referer');

        return str_starts_with($referer, $request->getSchemeAndHttpHost() . '/') ? $referer : '/';
    }
}
