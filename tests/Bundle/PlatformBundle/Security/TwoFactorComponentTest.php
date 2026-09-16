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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidWorx\Platform\PlatformBundle\Twig\Components\Security\TwoFactor;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\ProfileUser;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\StubUserProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use function array_keys;
use function restore_exception_handler;

/**
 * Mounts the two-factor component for real and drives its live actions.
 *
 * The verification form carries a CSRF token, and that token has to survive the trip out to the
 * browser and back through `formValues` — the live component submits the form from its own props,
 * not from the POST body. Nothing else in the suite covers that: the rendering tests render the
 * template with hand-built variables, and a unit test of the actions never builds the props at all.
 */
#[CoversClass(TwoFactor::class)]
final class TwoFactorComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    private ProfileUser $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        // The same instance the firewall hands the component, so mutations made across the HTTP
        // boundary are visible here.
        $this->user = $this->service(StubUserProvider::class, StubUserProvider::class)->user();

        $request = Request::create('/profile/two-factor');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->service('request_stack', RequestStack::class)->push($request);
        $this->service('security.token_storage', TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($this->user, 'main', $this->user->getRoles()));
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    /**
     * The token has to be in the values the component hands the browser, because that is what comes
     * back and gets submitted. Without it every verification fails as an invalid CSRF token.
     */
    public function testTheVerificationFormCarriesItsCsrfTokenInTheComponentState(): void
    {
        $component = $this->mount();

        self::assertSame(['code', 'secret', '_token'], array_keys($component->formValues));
        self::assertNotSame('', $component->formValues['_token']);
    }

    /**
     * The round trip in full, over HTTP: the browser sends back only the field it changed, and the
     * server rebuilds the rest from the signed props. If the token does not survive that, every
     * verification fails as an invalid CSRF token however correct the code is.
     *
     * The code here is wrong — the stub authenticator rejects every code — so a 422 is expected.
     * What matters is which field it complains about.
     */
    public function testSubmittingOnlyTheChangedFieldStillCarriesTheCsrfToken(): void
    {
        try {
            $this->live()->submitForm([
                'two_factor_verify' => [
                    'code' => '123456',
                ],
            ], 'enableTOTPAuth');

            self::fail('The stub authenticator rejects every code, so validation should have failed.');
        } catch (UnprocessableEntityHttpException $e) {
            self::assertStringNotContainsString(
                'CSRF',
                $e->getMessage(),
                'The CSRF token did not survive the round trip when only one field was sent back.',
            );
            self::assertStringContainsString('two_factor_verify.code', $e->getMessage());
        }
    }

    /**
     * Turning on a second factor mints backup codes, because losing the factor without them means
     * losing the account.
     */
    public function testEnablingEmailAuthenticationGeneratesBackupCodes(): void
    {
        $component = $this->mount();

        self::assertSame([], $this->user->getBackupCodes());

        $component->enableEmailAuth();

        self::assertTrue($this->user->isEmailAuthEnabled());
        self::assertNotSame([], $this->user->getBackupCodes());
    }

    /**
     * Turning the last factor off takes the codes with it — they unlock nothing once there is no
     * second step, and leaving them behind is a live secret nobody is watching.
     */
    public function testDisablingTheLastFactorClearsTheBackupCodes(): void
    {
        $component = $this->mount();
        $component->enableEmailAuth();

        self::assertNotSame([], $this->user->getBackupCodes());

        $component->disableEmailAuth();

        self::assertFalse($this->user->isEmailAuthEnabled());
        self::assertSame([], $this->user->getBackupCodes());
    }

    private function live(): TestLiveComponent
    {
        return $this->createLiveComponent('Platform:Security:TwoFactor')->actingAs($this->user);
    }

    private function mount(): TwoFactor
    {
        $component = $this->live()->component();

        self::assertInstanceOf(TwoFactor::class, $component);

        return $component;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TwoFactorTestKernel('test', true);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    private function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);

        self::assertInstanceOf($type, $service);

        return $service;
    }
}
