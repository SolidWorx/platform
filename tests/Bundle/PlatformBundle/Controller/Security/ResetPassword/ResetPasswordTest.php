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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Controller\Security\ResetPassword;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\RequestResetPassword;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\ResetPassword;
use SolidWorx\Platform\PlatformBundle\Form\Type\Security\ResetPasswordType;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicyInterface;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\FakeResetPasswordHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Translation\IdentityTranslator;
use SymfonyCasts\Bundle\ResetPassword\Exception\ExpiredResetPasswordTokenException;

#[CoversClass(ResetPassword::class)]
final class ResetPasswordTest extends ResetPasswordTestCase
{
    private const string STRONG_PASSWORD = 'Correct-Horse-Battery-Staple-42';

    private int $flushes = 0;

    /**
     * Kept out of the URL, so it cannot leak through the Referer header or the history.
     */
    public function testTheLinkMovesTheTokenIntoTheSessionAndOutOfTheUrl(): void
    {
        $response = $this->reset(Request::create('/reset-password/reset/' . FakeResetPasswordHelper::TOKEN), FakeResetPasswordHelper::TOKEN);

        self::assertSame('/reset-password/reset', $response->headers->get('Location'));
    }

    public function testThePageAsksForTheNewPasswordTwiceUnderThePasswordRules(): void
    {
        $this->openLink();

        $response = $this->reset(Request::create('/reset-password/reset'));
        $html = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('name="reset_password[newPassword][first]"', $html);
        self::assertStringContainsString('name="reset_password[newPassword][second]"', $html);
        self::assertStringContainsString('Password requirements', $html);
        self::assertStringContainsString('href="/login"', $html);
    }

    public function testWithoutATokenTheUserIsSentToAskForANewLink(): void
    {
        $response = $this->reset(Request::create('/reset-password/reset'));

        self::assertSame(RequestResetPassword::PATH, $response->headers->get('Location'));
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('error', $response->getFlashes()[0]['type']->value);
    }

    public function testAnExpiredLinkSaysSo(): void
    {
        $this->openLink();
        $this->helper->rejection = new ExpiredResetPasswordTokenException();

        $response = $this->reset(Request::create('/reset-password/reset'));

        self::assertSame(RequestResetPassword::PATH, $response->headers->get('Location'));
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('The link in your email is expired. Please try to reset your password again.', $response->getFlashes()[0]['message']);
    }

    public function testAPasswordThatBreaksTheRulesIsRejected(): void
    {
        $this->openLink();

        $response = $this->submit('short');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame([], $this->helper->removed, 'The link stays usable until a valid password is chosen.');
        self::assertSame(0, $this->flushes);
    }

    public function testANewPasswordIsHashedTheLinkSpentAndTheUserSentToSignIn(): void
    {
        $this->openLink();

        $response = $this->submit(self::STRONG_PASSWORD);

        self::assertSame('/login', $response->headers->get('Location'));
        self::assertSame('hashed:' . self::STRONG_PASSWORD, $this->user->getPassword());
        self::assertSame([FakeResetPasswordHelper::TOKEN], $this->helper->removed);
        self::assertSame(1, $this->flushes);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('success', $response->getFlashes()[0]['type']->value);
    }

    /**
     * Opening the link proves the user reads mail at the address, which is all verification asks.
     */
    public function testResettingThePasswordVerifiesTheEmailAddress(): void
    {
        $this->user->setVerified(false);
        $this->openLink();

        $this->submit(self::STRONG_PASSWORD);

        self::assertTrue($this->user->isVerified());
    }

    public function testTheSpentTokenIsForgotten(): void
    {
        $this->openLink();
        $this->submit(self::STRONG_PASSWORD);

        $response = $this->reset(Request::create('/reset-password/reset'));

        self::assertSame(RequestResetPassword::PATH, $response->headers->get('Location'));
    }

    private function openLink(): void
    {
        $this->reset(Request::create('/reset-password/reset/' . FakeResetPasswordHelper::TOKEN), FakeResetPasswordHelper::TOKEN);
    }

    private function submit(string $password): Response
    {
        $token = $this->csrfToken(ResetPasswordType::class);

        return $this->reset(Request::create('/reset-password/reset', 'POST', [
            'reset_password' => [
                'newPassword' => [
                    'first' => $password,
                    'second' => $password,
                ],
                '_token' => $token,
            ],
        ]));
    }

    private function reset(Request $request, ?string $token = null): Response
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushes;
        });

        $hasher = self::createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturnCallback(
            static fn (PasswordAuthenticatedUserInterface $user, string $plain): string => 'hashed:' . $plain,
        );

        $controller = new ResetPassword(
            $this->helper,
            $entityManager,
            $hasher,
            $this->service(PasswordPolicyInterface::class, PasswordPolicyInterface::class),
            new IdentityTranslator(),
            '@SolidWorxPlatform/ResetPassword/reset.html.twig',
            'main',
        );
        $this->wire($controller);

        return $controller($this->push($request), $token);
    }
}
