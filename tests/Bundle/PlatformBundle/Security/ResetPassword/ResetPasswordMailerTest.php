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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security\ResetPassword;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\AbstractLogger;
use SolidWorx\Platform\PlatformBundle\Exception\UntrustedLinkHostException;
use SolidWorx\Platform\PlatformBundle\Security\EmailLinkContext;
use SolidWorx\Platform\PlatformBundle\Security\ResetPassword\ResetPasswordMailer;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Controller\Security\ResetPassword\ResetPasswordTestCase;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\FakeResetPasswordHelper;
use Stringable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;
use Throwable;

#[CoversClass(ResetPasswordMailer::class)]
final class ResetPasswordMailerTest extends ResetPasswordTestCase
{
    /**
     * @var list<RawMessage>
     */
    private array $sent = [];

    private ?Throwable $mailFailure = null;

    /**
     * @var list<string>
     */
    private array $logged = [];

    private int $lookups = 0;

    public function testAnEnabledUserIsMailedALinkAndTheTokenReturned(): void
    {
        $token = $this->mailer()->send('ada@example.com');

        self::assertNotNull($token);
        self::assertSame(FakeResetPasswordHelper::TOKEN, $token->getToken());
        self::assertCount(1, $this->sent);

        $email = $this->sent[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('ada@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('Reset your password', $email->getSubject());
        self::assertSame('@SolidWorxPlatform/ResetPassword/email.html.twig', $email->getHtmlTemplate());
        self::assertSame(['user', 'resetUrl', 'expiresAtMessageKey', 'expiresAtMessageData'], array_keys($email->getContext()));
        self::assertSame($this->user, $email->getContext()['user']);
        self::assertSame('http://localhost/reset-password/reset/' . FakeResetPasswordHelper::TOKEN, $email->getContext()['resetUrl']);
    }

    public function testAnUnknownAddressReturnsNullWithoutAnEmail(): void
    {
        self::assertNull($this->mailer()->send('nobody@example.com'));
        self::assertSame([], $this->sent);
        self::assertSame([], $this->helper->issuedFor);
    }

    public function testADisabledUserReturnsNull(): void
    {
        $this->user->setEnabled(false);

        self::assertNull($this->mailer()->send('ada@example.com'));
        self::assertSame([], $this->sent);
    }

    public function testAThrottledRequestReturnsNull(): void
    {
        $this->helper->throttled = true;

        self::assertNull($this->mailer()->send('ada@example.com'));
        self::assertSame([], $this->sent);
    }

    public function testAMailerFailureIsLoggedTheRequestRemovedAndNullReturned(): void
    {
        $this->mailFailure = new TransportException('Connection refused.');

        self::assertNull($this->mailer()->send('ada@example.com'));
        self::assertSame([FakeResetPasswordHelper::TOKEN], $this->helper->removed);
        self::assertSame(['The password reset email could not be sent.'], $this->logged);
    }

    public function testAnUntrustedHostIsRefusedBeforeAnyLookup(): void
    {
        $this->push(Request::create('/'));

        try {
            $this->mailer(debug: false)->send('ada@example.com');
            self::fail('The untrusted host was not refused.');
        } catch (UntrustedLinkHostException) {
            self::assertSame(0, $this->lookups);
            self::assertSame([], $this->sent);
        }
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

    private function mailer(bool $debug = true): ResetPasswordMailer
    {
        $user = $this->user;

        $repository = self::createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            function (array $criteria) use ($user): ?object {
                ++$this->lookups;

                return $criteria['email'] === $user->getEmail() && $criteria['enabled'] === $user->isEnabled() ? $user : null;
            },
        );

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $mailer = new class($this) implements MailerInterface {
            public function __construct(
                private readonly ResetPasswordMailerTest $test,
            ) {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->test->record($message);
            }
        };

        $logger = new class($this) extends AbstractLogger {
            public function __construct(
                private readonly ResetPasswordMailerTest $test,
            ) {
            }

            public function log($level, string | Stringable $message, array $context = []): void
            {
                $this->test->log((string) $message);
            }
        };

        $router = $this->service('router', RouterInterface::class);

        return new ResetPasswordMailer(
            $this->helper,
            $entityManager,
            $mailer,
            new IdentityTranslator(),
            $logger,
            new EmailLinkContext($router, $this->service('request_stack', RequestStack::class), null, $debug),
            $router,
            '@SolidWorxPlatform/ResetPassword/email.html.twig',
            'Reset your password',
        );
    }
}
