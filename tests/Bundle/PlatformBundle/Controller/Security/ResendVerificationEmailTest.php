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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Controller\Security;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendVerificationEmail;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\EmailVerifier;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\ProfileUser;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\IdentityTranslator;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use function time;

#[CoversClass(ResendVerificationEmail::class)]
#[UsesClass(EmailVerifier::class)]
final class ResendVerificationEmailTest extends TestCase
{
    private ProfileUser $user;

    private Request $request;

    /**
     * @var list<RawMessage>
     */
    private array $sent = [];

    private bool $mailerFails = false;

    protected function setUp(): void
    {
        $this->user = new ProfileUser();
        $this->user->setEmail('ada@example.com');

        $this->request = Request::create('https://app.example.com/verify/resend', 'POST', [
            '_token' => 'valid',
        ]);
        $this->request->setSession(new Session(new MockArraySessionStorage()));
    }

    public function testSendsANewLinkToAnUnverifiedUser(): void
    {
        $this->resend();

        self::assertCount(1, $this->sent);
        self::assertSame([
            'success' => ['A new verification email has been sent. Please check your inbox.'],
        ], $this->flashes());
    }

    public function testDoesNotSendToAUserWhoIsAlreadyVerified(): void
    {
        $this->user->setVerified(true);

        $this->resend();

        self::assertCount(0, $this->sent);
        self::assertArrayHasKey('info', $this->flashes());
    }

    /**
     * Otherwise the button would let anyone signed in flood an inbox.
     */
    public function testRefusesASecondLinkWithinTheCooldown(): void
    {
        $this->resend();
        $this->resend();

        self::assertCount(1, $this->sent);
        self::assertArrayHasKey('warning', $this->flashes());
    }

    public function testSendsAgainOnceTheCooldownHasPassed(): void
    {
        $this->resend();
        $this->request->getSession()->set('_solidworx_platform_verification_email_sent_at', time() - ResendVerificationEmail::COOLDOWN);
        $this->resend();

        self::assertCount(2, $this->sent);
    }

    /**
     * A failed send must not start the cooldown, or the user would be told to wait for an email
     * that is never coming.
     */
    public function testReportsAFailedSendAndLetsTheUserTryAgain(): void
    {
        $this->mailerFails = true;
        $this->resend();

        self::assertArrayHasKey('error', $this->flashes());

        $this->mailerFails = false;
        $this->resend();

        self::assertCount(1, $this->sent);
    }

    public function testRefusesARequestWithoutAValidCsrfToken(): void
    {
        $this->request->request->set('_token', 'forged');

        $this->resend();

        self::assertCount(0, $this->sent);
        self::assertArrayHasKey('error', $this->flashes());
    }

    public function testReturnsToThePageTheButtonWasOn(): void
    {
        $this->request->headers->set('referer', 'https://app.example.com/invoices/1');

        self::assertSame('https://app.example.com/invoices/1', $this->resend());
    }

    public function testDoesNotFollowARefererToAnotherSite(): void
    {
        $this->request->headers->set('referer', 'https://app.example.com.evil.test/phish');

        self::assertSame('/', $this->resend());
    }

    private function resend(): string
    {
        $requestStack = new RequestStack();
        $requestStack->push($this->request);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($this->user, 'main', $this->user->getRoles()));

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('security.token_storage', $tokenStorage);
        $container->set('security.csrf.token_manager', $this->csrfTokenManager());

        $controller = new ResendVerificationEmail($this->verifier());
        $controller->setContainer($container);

        return $controller($this->request)->getTargetUrl();
    }

    private function csrfTokenManager(): CsrfTokenManagerInterface
    {
        $manager = self::createStub(CsrfTokenManagerInterface::class);
        $manager->method('isTokenValid')->willReturnCallback(
            static fn (CsrfToken $token): bool => $token->getId() === ResendVerificationEmail::CSRF_TOKEN_ID && $token->getValue() === 'valid',
        );

        return $manager;
    }

    private function verifier(): EmailVerifier
    {
        $helper = self::createStub(VerifyEmailHelperInterface::class);
        $helper->method('generateSignature')->willReturn(
            new VerifyEmailSignatureComponents(new DateTimeImmutable('+7 days'), 'https://app.example.com/verify?signature=x', time()),
        );

        $mailer = new class($this) implements MailerInterface {
            public function __construct(
                private readonly ResendVerificationEmailTest $test,
            ) {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->test->record($message);
            }
        };

        return new EmailVerifier($helper, $mailer, self::createStub(EntityManagerInterface::class), new IdentityTranslator(), 'email.html.twig', 'Confirm');
    }

    public function record(RawMessage $message): void
    {
        if ($this->mailerFails) {
            throw new TransportException('Mail server is down.');
        }

        $this->sent[] = $message;
    }

    /**
     * @return array<string, list<string>>
     */
    private function flashes(): array
    {
        $session = $this->request->getSession();
        self::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);

        /** @var array<string, list<string>> */
        return $session->getFlashBag()->all();
    }
}
