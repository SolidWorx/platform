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

use Override;
use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\FakeResetPasswordHelper;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\ProfileUser;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Profile\ProfileTestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use function restore_exception_handler;

/**
 * Runs the reset-password controllers against the real templates, forms and router of
 * {@see ProfileTestKernel}; only the token helper, the database and the mailer are stand-ins.
 */
abstract class ResetPasswordTestCase extends KernelTestCase
{
    protected ProfileUser $user;

    protected FakeResetPasswordHelper $helper;

    protected SessionInterface $session;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->user = new ProfileUser();
        $this->user->setEmail('ada@example.com');
        $this->user->setEnabled(true);

        $this->helper = new FakeResetPasswordHelper($this->user);
        $this->session = new Session(new MockArraySessionStorage());
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    /**
     * Pushes the request, so the CSRF token and the flashes land in the test's session.
     */
    protected function push(Request $request): Request
    {
        $request->setSession($this->session);

        $requestStack = $this->service('request_stack', RequestStack::class);

        while ($requestStack->pop() instanceof Request) {
            // Empty the stack left by the previous request.
        }

        $requestStack->push($request);

        return $request;
    }

    /**
     * A valid CSRF token for a form of `$type`, read the way the browser would: off the rendered form.
     *
     * @param class-string<FormTypeInterface<mixed>> $type
     */
    protected function csrfToken(string $type): string
    {
        $this->push(Request::create('/'));

        $token = $this->service(ProfileTestKernel::FORM_FACTORY, FormFactoryInterface::class)
            ->create($type)
            ->createView()['_token']
            ->vars['value'];

        self::assertIsString($token);

        return $token;
    }

    protected function wire(BaseController $controller): void
    {
        $container = new Container();
        $container->set('request_stack', $this->service('request_stack', RequestStack::class));
        $container->set('form.factory', $this->service(ProfileTestKernel::FORM_FACTORY, FormFactoryInterface::class));
        $container->set('twig', $this->service('twig', Environment::class));
        $container->set('router', $this->service('router', RouterInterface::class));

        $controller->setContainer($container);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function flashes(): array
    {
        self::assertInstanceOf(FlashBagAwareSessionInterface::class, $this->session);

        /** @var array<string, list<string>> */
        return $this->session->getFlashBag()->peekAll();
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new ProfileTestKernel('test', true);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    protected function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);

        self::assertInstanceOf($type, $service);

        return $service;
    }
}
