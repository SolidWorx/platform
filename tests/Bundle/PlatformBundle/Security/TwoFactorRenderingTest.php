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
use PHPUnit\Framework\Attributes\CoversNothing;
use SolidWorx\Platform\PlatformBundle\Form\Type\Security\TwoFactorVerifyType;
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
use Symfony\UX\TwigComponent\ComponentAttributes;
use Twig\Environment;
use Twig\Runtime\EscaperRuntime;
use function restore_exception_handler;
use function substr_count;

/**
 * Renders the two-factor settings template through the real Twig runtime.
 *
 * It exists because everything else about this template passes without it. The markup compiles, the
 * PHP is type-checked and the live actions are ordinary methods — and none of that catches a
 * variable that resolves to the wrong object only while rendering. `this` inside a component slot
 * is exactly that: inside `<twig:Ui:Modal>` it is the modal, not this component, so
 * `{{ this.qrContent }}` there fails at runtime and nowhere else.
 *
 * The component is not mounted — that needs Doctrine, the scheb services and a session. The
 * template is rendered directly with the variables the component exposes, which is enough, because
 * the template is the part with no other coverage.
 */
#[CoversNothing]
final class TwoFactorRenderingTest extends KernelTestCase
{
    private const string TEMPLATE = '@SolidWorxPlatform/Components/Security/two_factor.html.twig';

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
            ->setEmail('ada@example.com');

        $request = Request::create('/profile/two-factor');
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

    /**
     * Nothing is switched on: both methods offer to be enabled, and the recovery and trusted-device
     * cards stay out of the way until there is something to say.
     */
    public function testTheMethodsAreOfferedWhenNothingIsEnabledYet(): void
    {
        $html = $this->render();

        self::assertStringContainsString('Authenticator app', $html);
        self::assertStringContainsString('Email', $html);
        self::assertStringNotContainsString('Backup codes', $html);
        self::assertStringNotContainsString('Trusted devices', $html);
    }

    /**
     * The QR code is read from the component before the modal, because `this` inside a component
     * slot is the slot's own component. Getting that wrong is a runtime-only failure, so the data
     * URI actually reaching the `<img>` is worth asserting.
     */
    public function testTheSetupDialogRendersTheQrCodeAndTheKeyToTypeByHand(): void
    {
        $html = $this->render();

        self::assertStringContainsString('src="data:image/png;base64,QR"', $html);
        self::assertStringContainsString('JBSWY3DPEHPK3PXP', $html);
    }

    /**
     * Both steps are in the DOM the whole time — hidden, not removed — so the form the live
     * component receives is complete whichever step is on screen.
     */
    public function testBothSetupStepsAreRenderedAndOnlyTheSecondIsHidden(): void
    {
        $html = $this->render();

        self::assertStringContainsString('name="two_factor_verify[code]"', $html);
        self::assertStringContainsString('name="two_factor_verify[secret]"', $html);
        self::assertSame(3, substr_count($html, 'data-step="0"'), 'A pane and two footer buttons.');
        self::assertSame(3, substr_count($html, 'data-step="1"'), 'A pane and two footer buttons.');
    }

    /**
     * The footer buttons are siblings inside `.modal-footer` rather than one wrapper per step, so
     * they pick up the modal's own spacing and right alignment — the same as every other modal.
     * Grouping them would left-align the dismiss button in this modal and nowhere else.
     */
    public function testTheFooterButtonsSitDirectlyInTheModalFooter(): void
    {
        $html = $this->render();

        self::assertStringNotContainsString('justify-content-between', $html);
        self::assertMatchesRegularExpression(
            '~<div class="modal-footer">\s*<button~',
            $html,
            'A wrapper between the footer and its buttons breaks the shared spacing.',
        );
    }

    /**
     * The confirming action of a modal is the primary button, whichever modal it is. Regenerating
     * codes used to be a warning button, which made the same kind of action look different
     * depending on which dialog you opened.
     */
    public function testEveryModalConfirmsWithAPrimaryButton(): void
    {
        $this->user->enableEmailAuth(true);
        $this->user->setBackUpCodes(['AAAA-1111']);

        $html = $this->render();

        self::assertStringNotContainsString('btn-warning', $html);
        self::assertStringContainsString('Regenerate codes', $html);
    }

    /**
     * These land in a downloads folder beside every other file called `backup-codes.txt`, so the
     * application name is what tells two accounts apart.
     */
    public function testTheDownloadedFileIsNamedAfterTheApplication(): void
    {
        self::assertStringContainsString('filename-value="acme-platform-backup-codes.txt"', $this->render());
    }

    /**
     * A failed verification has to reopen on the step that failed rather than sending the user back
     * to the QR code, so the starting step comes from the server.
     */
    public function testAFailedVerificationReopensOnTheVerificationStep(): void
    {
        self::assertStringContainsString('step-value="0"', $this->render());
        self::assertStringContainsString('step-value="1"', $this->render(submittedAndInvalid: true));
    }

    public function testTheBackupCodesAppearOnceASecondFactorIsOn(): void
    {
        $this->user->enableEmailAuth(true);
        $this->user->setBackUpCodes(['AAAA-1111', 'BBBB-2222']);

        $html = $this->render();

        self::assertStringContainsString('Backup codes', $html);
        self::assertStringContainsString('AAAA-1111', $html);
        self::assertStringContainsString('BBBB-2222', $html);
        self::assertStringContainsString('2 codes remaining', $html);
    }

    /**
     * The codes reach the Stimulus controller as a value, which is what lets the browser build the
     * downloaded file without a route that returns recovery codes.
     */
    public function testTheBackupCodesAreHandedToTheDownloadController(): void
    {
        $this->user->enableEmailAuth(true);
        $this->user->setBackUpCodes(['AAAA-1111']);

        self::assertStringContainsString('codes-value="[&quot;AAAA-1111&quot;]"', $this->render());
    }

    /**
     * With TOTP already paired there is nothing to set up, so the dialog — and the QR code behind
     * it — is not rendered at all.
     */
    public function testTheSetupDialogIsGoneOnceTheAuthenticatorIsPaired(): void
    {
        $this->user->setTotpSecret('JBSWY3DPEHPK3PXP');

        $html = $this->render();

        self::assertStringNotContainsString('totp-setup-modal', $html);
        self::assertStringNotContainsString('data:image/png;base64,QR', $html);
        self::assertStringContainsString('Disable', $html);
    }

    public function testTheTrustedDeviceCardOnlyAppearsForATrustedBrowser(): void
    {
        self::assertStringNotContainsString('Trusted devices', $this->render());
        self::assertStringContainsString('Trusted devices', $this->render(deviceTrusted: true));
    }

    private function render(bool $submittedAndInvalid = false, bool $deviceTrusted = false): string
    {
        $form = $this->formFactory()->create(TwoFactorVerifyType::class, [
            'secret' => 'JBSWY3DPEHPK3PXP',
        ], [
            'secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        if ($submittedAndInvalid) {
            // An empty code fails `NotBlank`, which is the ordinary way this form comes back wrong.
            $form->submit([
                'code' => '',
                'secret' => 'JBSWY3DPEHPK3PXP',
            ]);
        }

        return $this->twig()->render(self::TEMPLATE, [
            'this' => new StubTwoFactorComponent(),
            'attributes' => new ComponentAttributes([], $this->service('twig.runtime.escaper', EscaperRuntime::class)),
            'form' => $form->createView(),
            'totpSecret' => 'JBSWY3DPEHPK3PXP',
            'showBackupCodes' => false,
            // Exposed by the component through `#[ExposeInTemplate]`; how it is built from the
            // application name is asserted in TwoFactorFilenameTest.
            'backupCodesFilename' => 'acme-platform-backup-codes.txt',
            'isDeviceTrusted' => $deviceTrusted,
        ]);
    }

    private function twig(): Environment
    {
        return $this->service('twig', Environment::class);
    }

    private function formFactory(): FormFactoryInterface
    {
        return $this->service(TwoFactorTestKernel::FORM_FACTORY, FormFactoryInterface::class);
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

final class StubTwoFactorComponent
{
    public function getQrContent(): string
    {
        return 'data:image/png;base64,QR';
    }
}
