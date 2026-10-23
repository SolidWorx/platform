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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Controller\Profile;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\EditProfile;
use SolidWorx\Platform\PlatformBundle\Enum\Flash;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use SolidWorx\Platform\PlatformBundle\Security\EmailLinkContext;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\EmailVerifier;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\ProfileUser;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Translation\IdentityTranslator;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use function array_map;

#[CoversClass(EditProfile::class)]
#[UsesClass(EmailVerifier::class)]
final class EditProfileTest extends TestCase
{
    private ProfileUser $user;

    /**
     * @var list<RawMessage>
     */
    private array $sent = [];

    protected function setUp(): void
    {
        $this->user = new ProfileUser();
        $this->user->setEmail('ada@example.com');
        $this->user->setVerified(verified: true);
    }

    /**
     * Otherwise a verified user could switch to any address and keep sending as verified.
     */
    public function testChangingTheEmailUnverifiesTheAccountAndMailsTheNewAddress(): void
    {
        $response = $this->submit('someone-else@example.com');

        $this->assertFalse($this->user->isVerified());
        $this->assertCount(1, $this->sent);
        $this->assertInstanceOf(Email::class, $this->sent[0]);
        $this->assertSame(['someone-else@example.com'], array_map(static fn (Address $address): string => $address->getAddress(), $this->sent[0]->getTo()));
        $this->assertSame(Flash::Info, $response->getFlashes()[0]['type']);
    }

    public function testKeepingTheSameEmailKeepsTheAccountVerified(): void
    {
        $this->submit('ada@example.com');

        $this->assertTrue($this->user->isVerified());
        $this->assertCount(0, $this->sent);
    }

    public function testWithVerificationTurnedOffAnEmailChangeIsSavedAsBefore(): void
    {
        $this->submit('someone-else@example.com', withVerifier: false);

        $this->assertTrue($this->user->isVerified());
        $this->assertSame('someone-else@example.com', $this->user->getEmail());
    }

    private function submit(string $email, bool $withVerifier = true): RedirectResponse
    {
        $user = $this->user;

        $form = self::createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnCallback(static function () use ($form, $user, $email): FormInterface {
            $user->setEmail($email);

            return $form;
        });
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $formFactory = self::createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $router = self::createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/profile');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($this->user, 'main', $this->user->getRoles()));

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('security.token_storage', $tokenStorage);

        $controller = new EditProfile(
            self::createStub(EntityManagerInterface::class),
            'edit.html.twig',
            FormType::class,
            $withVerifier ? $this->verifier() : null,
        );
        $controller->setContainer($container);

        $response = $controller(Request::create('/profile/edit', 'POST'));
        $this->assertInstanceOf(RedirectResponse::class, $response);

        return $response;
    }

    private function verifier(): EmailVerifier
    {
        $helper = self::createStub(VerifyEmailHelperInterface::class);
        $helper->method('generateSignature')->willReturn(
            new VerifyEmailSignatureComponents(CarbonImmutable::now()->addDays(7), 'https://app.example.com/verify?signature=x', Carbon::now()->getTimestamp()),
        );

        $mailer = new readonly class($this) implements MailerInterface {
            public function __construct(
                private EditProfileTest $test,
            ) {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->test->record($message);
            }
        };

        return new EmailVerifier($helper, $mailer, self::createStub(EntityManagerInterface::class), new IdentityTranslator(), 'email.html.twig', 'Confirm', new EmailLinkContext(self::createStub(RouterInterface::class), new RequestStack()));
    }

    public function record(RawMessage $message): void
    {
        $this->sent[] = $message;
    }
}
