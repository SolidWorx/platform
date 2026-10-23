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
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\AbstractLogger;
use RuntimeException;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\CheckResetPasswordEmail;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\RequestResetPassword;
use SolidWorx\Platform\PlatformBundle\Exception\UntrustedLinkHostException;
use SolidWorx\Platform\PlatformBundle\Form\Type\Security\ResetPasswordRequestType;
use SolidWorx\Platform\PlatformBundle\Security\EmailLinkContext;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\FakeResetPasswordHelper;
use Stringable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Exception\LogicException as MimeLogicException;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use Throwable;

#[CoversClass(RequestResetPassword::class)]
#[CoversClass(CheckResetPasswordEmail::class)]
final class RequestResetPasswordTest extends ResetPasswordTestCase
{
    /**
     * @var list<RawMessage>
     */
    private array $sent = [];

    private ?Throwable $mailFailure = null;

    private ?string $baseUrl = null;

    private bool $debug = true;

    /**
     * @var list<string>
     */
    private array $logged = [];

    public function testTheFormAsksForAnEmailAddressAndLinksBackToTheLoginPage(): void
    {
        $response = $this->controller()($this->push(Request::create(RequestResetPassword::PATH)));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('name="reset_password_request[email]"', (string) $response->getContent());
        self::assertStringContainsString('href="/login"', (string) $response->getContent());
    }

    public function testAKnownAddressIsMailedALinkToTheResetPage(): void
    {
        $response = $this->submit('ada@example.com');

        self::assertSame('/reset-password/check-email', $response->headers->get('Location'));
        self::assertCount(1, $this->sent);

        $email = $this->sent[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('ada@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('Reset your password', $email->getSubject());
        self::assertSame('http://localhost/reset-password/reset/' . FakeResetPasswordHelper::TOKEN, $email->getContext()['resetUrl']);
    }

    /**
     * Otherwise the form would tell anyone which addresses have an account.
     */
    public function testAnUnknownAddressLandsOnTheSamePageWithoutAnEmail(): void
    {
        $response = $this->submit('nobody@example.com');

        self::assertSame('/reset-password/check-email', $response->headers->get('Location'));
        self::assertCount(0, $this->sent);
        self::assertSame([], $this->helper->issuedFor);
    }

    public function testADisabledAccountIsNotMailedALink(): void
    {
        $this->user->setEnabled(false);

        $this->submit('ada@example.com');

        self::assertCount(0, $this->sent);
    }

    /**
     * The link sent a moment ago is still valid; saying "too many requests" here would confirm
     * the account exists.
     */
    public function testAThrottledRequestLooksLikeAnyOther(): void
    {
        $this->helper->throttled = true;

        $response = $this->submit('ada@example.com');

        self::assertSame('/reset-password/check-email', $response->headers->get('Location'));
        self::assertCount(0, $this->sent);
    }

    public function testAMailServerThatIsDownIsLoggedAndTheRequestDropped(): void
    {
        $this->mailFailure = new TransportException('Connection refused.');

        $response = $this->submit('ada@example.com');

        self::assertSame('/reset-password/check-email', $response->headers->get('Location'));
        self::assertSame([FakeResetPasswordHelper::TOKEN], $this->helper->removed, 'Dropped, so the throttle does not hold up the next try.');
        self::assertSame(['The password reset email could not be sent.'], $this->logged);
    }

    /**
     * A misconfigured mailer (here: no sender address) must not turn into an error page only for
     * addresses that have an account.
     */
    public function testAnyMailerFailureEndsLikeAnUnknownAddress(): void
    {
        $this->mailFailure = new MimeLogicException('An email must have a "From" or a "Sender" header.');

        $response = $this->submit('ada@example.com');

        self::assertSame('/reset-password/check-email', $response->headers->get('Location'));
        self::assertCount(1, $this->logged);
    }

    public function testTheCheckEmailPageSaysHowLongTheLinkIsValidFor(): void
    {
        $this->submit('ada@example.com');

        $token = $this->session->get(RequestResetPassword::SESSION_TOKEN);
        self::assertInstanceOf(ResetPasswordToken::class, $token);

        $response = $this->checkEmail();

        self::assertStringContainsString('The link expires in', (string) $response->getContent());
        self::assertStringContainsString('href="/login"', (string) $response->getContent());
        self::assertStringContainsString('href="/reset-password"', (string) $response->getContent());
    }

    /**
     * Only the expiry is needed later, and a session is not where a secret should sit.
     */
    public function testTheTokenKeptInTheSessionHasItsSecretCleared(): void
    {
        $this->submit('ada@example.com');

        $token = $this->session->get(RequestResetPassword::SESSION_TOKEN);
        self::assertInstanceOf(ResetPasswordToken::class, $token);

        try {
            $token->getToken();
            self::fail('The token was kept in the session in full.');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('clearToken', $runtimeException->getMessage());
        }
    }

    public function testTheCheckEmailPageOpenedDirectlyReadsTheSame(): void
    {
        self::assertStringContainsString('The link expires in', (string) $this->checkEmail()->getContent());
    }

    public function testTheRateLimitIsReportedOnTheForm(): void
    {
        $limiter = new RateLimiterFactory([
            'id' => 'reset',
            'policy' => 'fixed_window',
            'limit' => 1,
            'interval' => '15 minutes',
        ], new InMemoryStorage());

        $this->submit('nobody@example.com', $limiter);
        $response = $this->submit('ada@example.com', $limiter);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertStringContainsString('Too many password reset requests', (string) $response->getContent());
        self::assertCount(0, $this->sent);
    }

    /**
     * The link must not follow a `Host` header an attacker can choose.
     */
    public function testTheConfiguredBaseUrlIsUsedForTheLink(): void
    {
        $this->baseUrl = 'https://app.example.com/';

        $this->submit('ada@example.com');

        self::assertInstanceOf(TemplatedEmail::class, $this->sent[0]);
        self::assertSame('https://app.example.com/reset-password/reset/' . FakeResetPasswordHelper::TOKEN, $this->sent[0]->getContext()['resetUrl']);
    }

    public function testWithoutABaseUrlOrTrustedHostsThePageRefusesToRunOutsideDebugMode(): void
    {
        $this->debug = false;

        $this->expectException(UntrustedLinkHostException::class);

        $this->controller()($this->push(Request::create(RequestResetPassword::PATH)));
    }

    public function testTrustedHostsAreEnoughToUseTheRequestHost(): void
    {
        $this->debug = false;
        Request::setTrustedHosts(['^localhost$']);

        try {
            $this->submit('ada@example.com');
        } finally {
            Request::setTrustedHosts([]);
        }

        self::assertCount(1, $this->sent);
    }

    public function testAnInvalidAddressIsRejected(): void
    {
        $response = $this->submit('not-an-email');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertCount(0, $this->sent);
    }

    public function record(RawMessage $message): void
    {
        if ($this->mailFailure instanceof Throwable) {
            throw $this->mailFailure;
        }

        $this->sent[] = $message;
    }

    public function log(string $message): void
    {
        $this->logged[] = $message;
    }

    private function submit(string $email, ?RateLimiterFactory $limiter = null): Response
    {
        $token = $this->csrfToken(ResetPasswordRequestType::class);

        $request = $this->push(Request::create(RequestResetPassword::PATH, 'POST', [
            'reset_password_request' => [
                'email' => $email,
                '_token' => $token,
            ],
        ]));

        return $this->controller($limiter)($request);
    }

    private function checkEmail(): Response
    {
        $controller = new CheckResetPasswordEmail($this->helper, '@SolidWorxPlatform/ResetPassword/check_email.html.twig', 'main');
        $this->wire($controller);

        return $controller($this->push(Request::create(CheckResetPasswordEmail::PATH)));
    }

    private function controller(?RateLimiterFactory $limiter = null): RequestResetPassword
    {
        $user = $this->user;

        $repository = self::createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?object => $criteria['email'] === $user->getEmail() && $criteria['enabled'] === $user->isEnabled() ? $user : null,
        );

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $mailer = new class($this) implements MailerInterface {
            public function __construct(
                private readonly RequestResetPasswordTest $test,
            ) {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->test->record($message);
            }
        };

        $logger = new class($this) extends AbstractLogger {
            public function __construct(
                private readonly RequestResetPasswordTest $test,
            ) {
            }

            public function log($level, string | Stringable $message, array $context = []): void
            {
                $this->test->log((string) $message);
            }
        };

        $controller = new RequestResetPassword(
            $this->helper,
            $entityManager,
            $mailer,
            new IdentityTranslator(),
            $logger,
            new EmailLinkContext(
                $this->service('router', RouterInterface::class),
                $this->service('request_stack', RequestStack::class),
                $this->baseUrl,
                $this->debug,
            ),
            '@SolidWorxPlatform/ResetPassword/request.html.twig',
            '@SolidWorxPlatform/ResetPassword/email.html.twig',
            'Reset your password',
            'main',
            limiter: $limiter,
        );
        $this->wire($controller);

        return $controller;
    }
}
