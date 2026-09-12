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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Profile;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ChangePassword;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\EditProfile;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ShowProfile;
use SolidWorx\Platform\PlatformBundle\Controller\Security\TwoFactorConfiguration;
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ChangePasswordType;
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ProfileType;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicyInterface;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\ProfileUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;
use function restore_exception_handler;

/**
 * Renders the three profile templates through the real Twig runtime.
 *
 * These templates are a customisation surface — applications are told to extend them and override
 * blocks — so the blocks, the macros and the fields they render are a contract worth asserting,
 * not just markup.
 */
#[CoversNothing]
final class ProfileRenderingTest extends KernelTestCase
{
    private ProfileUser $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->user = new ProfileUser();
        $this->user
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setEmail('ada@example.com')
            ->setMobile('+27 82 000 0000');

        $request = Request::create(ShowProfile::PATH);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->requestStack()->push($request);

        $this->tokenStorage()->setToken(new UsernamePasswordToken($this->user, 'main', $this->user->getRoles()));
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        // Booting the kernel in debug mode registers a Symfony exception handler that it does not
        // remove; restore it so PHPUnit does not flag the test as risky.
        restore_exception_handler();
    }

    public function testTheProfilePageShowsEveryDetailThePlatformHolds(): void
    {
        $html = $this->renderShowPage();

        self::assertStringContainsString('Ada', $html);
        self::assertStringContainsString('Lovelace', $html);
        self::assertStringContainsString('ada@example.com', $html);
        self::assertStringContainsString('+27 82 000 0000', $html);
    }

    public function testTheProfilePageLinksToBothWaysOfChangingSomething(): void
    {
        $html = $this->renderShowPage();

        self::assertStringContainsString('href="' . EditProfile::PATH . '"', $html);
        self::assertStringContainsString('href="' . ChangePassword::PATH . '"', $html);
    }

    /**
     * An empty field has to read as "nothing here yet" rather than as a rendering bug.
     */
    public function testADetailThatHasNotBeenFilledInShowsADash(): void
    {
        $this->user->setMobile(null);

        self::assertStringContainsString('&mdash;', $this->renderShowPage());
    }

    /**
     * The two-factor route only exists when 2FA is enabled, so the entry has to disappear with it
     * — otherwise the page cannot render at all for an application that left 2FA off.
     */
    public function testTheTwoFactorEntryIsOnlyShownWhenTwoFactorIsEnabled(): void
    {
        self::assertStringNotContainsString(TwoFactorConfiguration::PATH, $this->renderShowPage(twoFactorEnabled: false));
        self::assertStringContainsString(TwoFactorConfiguration::PATH, $this->renderShowPage(twoFactorEnabled: true));
    }

    public function testTheEditPageRendersEveryFieldOfTheProfileForm(): void
    {
        $html = $this->renderEditPage();

        self::assertStringContainsString('name="profile[firstName]"', $html);
        self::assertStringContainsString('name="profile[lastName]"', $html);
        self::assertStringContainsString('name="profile[email]"', $html);
        self::assertStringContainsString('name="profile[mobile]"', $html);
    }

    public function testTheEditFormPostsBackToTheEditPage(): void
    {
        $html = $this->renderEditPage();

        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('name="profile[_token]"', $html);
    }

    public function testTheEditFormIsPrefilledWithTheCurrentDetails(): void
    {
        self::assertStringContainsString('value="ada@example.com"', $this->renderEditPage());
    }

    public function testTheChangePasswordPageAsksForTheCurrentPasswordAndTheNewOneTwice(): void
    {
        $html = $this->renderChangePasswordPage();

        self::assertStringContainsString('name="change_password[currentPassword]"', $html);
        self::assertStringContainsString('name="change_password[newPassword][first]"', $html);
        self::assertStringContainsString('name="change_password[newPassword][second]"', $html);
    }

    /**
     * The fields are rendered one at a time, so the token — which `form_end()` emits with the
     * rest — is the easiest thing to lose to a template edit.
     */
    public function testTheChangePasswordFormCarriesACsrfToken(): void
    {
        self::assertStringContainsString('name="change_password[_token]"', $this->renderChangePasswordPage());
    }

    /**
     * Rules that are enforced but never stated are just failed submissions, so the page has to
     * list what the policy actually requires.
     */
    public function testTheChangePasswordPageListsTheRulesInForce(): void
    {
        $html = $this->renderChangePasswordPage();

        foreach ($this->passwordPolicy()->requirements() as $requirement) {
            self::assertStringContainsString($requirement, $html);
        }
    }

    /**
     * Browsers offer to fill and to save passwords based on these hints; getting them wrong is
     * what makes a password manager overwrite the wrong entry.
     */
    public function testThePasswordFieldsCarryTheRightAutocompleteHints(): void
    {
        $html = $this->renderChangePasswordPage();

        self::assertStringContainsString('autocomplete="current-password"', $html);
        self::assertStringContainsString('autocomplete="new-password"', $html);
    }

    private function renderShowPage(bool $twoFactorEnabled = false): string
    {
        return $this->twig()->render('@SolidWorxPlatform/Profile/show.html.twig', [
            'user' => $this->user,
            'two_factor_enabled' => $twoFactorEnabled,
        ]);
    }

    private function renderEditPage(): string
    {
        $form = $this->formFactory()->create(ProfileType::class, $this->user);

        return $this->twig()->render('@SolidWorxPlatform/Profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $this->user,
        ]);
    }

    private function renderChangePasswordPage(): string
    {
        $form = $this->formFactory()->create(ChangePasswordType::class);

        return $this->twig()->render('@SolidWorxPlatform/Profile/change_password.html.twig', [
            'form' => $form->createView(),
            'user' => $this->user,
            'password_requirements' => $this->passwordPolicy()->requirements(),
        ]);
    }

    private function twig(): Environment
    {
        return $this->service('twig', Environment::class);
    }

    private function formFactory(): FormFactoryInterface
    {
        return $this->service(ProfileTestKernel::FORM_FACTORY, FormFactoryInterface::class);
    }

    private function passwordPolicy(): PasswordPolicyInterface
    {
        return $this->service(PasswordPolicyInterface::class, PasswordPolicyInterface::class);
    }

    private function requestStack(): RequestStack
    {
        return $this->service('request_stack', RequestStack::class);
    }

    private function tokenStorage(): TokenStorageInterface
    {
        return $this->service('security.token_storage', TokenStorageInterface::class);
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
    private function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);

        self::assertInstanceOf($type, $service);

        return $service;
    }
}
