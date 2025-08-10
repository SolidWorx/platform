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

namespace SolidWorx\Platform\PlatformBundle\Twig\Components\Security;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Override;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Trusted\TrustedDeviceManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Trusted\TrustedDeviceTokenStorage;
use SolidWorx\Platform\PlatformBundle\Contracts\Doctrine\Repository\UserRepository;
use SolidWorx\Platform\PlatformBundle\Contracts\Security\TwoFactor\UserTwoFactorInterface;
use SolidWorx\Platform\PlatformBundle\Form\Type\Security\TwoFactorVerifyType;
use SolidWorx\Platform\PlatformBundle\Security\TwoFactor\BackupCodeGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PreMount;
use function assert;

#[AsLiveComponent(name: 'Platform:Security:TwoFactor', template: '@SolidWorxPlatform/Components/Security/two_factor.html.twig')]
final class TwoFactor extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public string $totpSecret;

    public bool $showBackupCodes = false;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        #[Autowire(service: 'scheb_two_factor.trusted_token_storage')]
        private readonly TrustedDeviceTokenStorage $trustedDeviceTokenStorage,
        #[Autowire(service: 'scheb_two_factor.default_trusted_device_manager')]
        private readonly TrustedDeviceManagerInterface $trustedDeviceManager,
        private readonly BackupCodeGeneratorInterface $backupCodeGenerator,
    ) {
    }

    #[PreMount()]
    public function preMount(): void
    {
        $this->totpSecret = $this->totpAuthenticator->generateSecret();
    }

    #[LiveAction()]
    public function enableEmailAuth(): void
    {
        $user = $this->getUser();
        if (! $user instanceof UserTwoFactorInterface) {
            return;
        }

        $user->enableEmailAuth(true);

        if ($user->getBackupCodes() === []) {
            $user->setBackUpCodes($this->generateBackupCodes());
            $this->showBackupCodes = true;
        }

        $this->userRepository->save($user);
    }

    #[LiveAction()]
    public function disableEmailAuth(): void
    {
        $user = $this->getUser();
        if (! $user instanceof UserTwoFactorInterface) {
            return;
        }

        $user->enableEmailAuth(false);

        if (! $user->is2FaEnabled()) {
            $user->setBackUpCodes([]);
        }

        $this->userRepository->save($user);
    }

    public function getQrContent(): string
    {
        $user = $this->getUser();
        assert($user instanceof UserTwoFactorInterface);

        if (! $user->isTotpAuthenticationEnabled()) {
            $user = clone $user;
            $user->setTotpSecret($this->totpSecret);
        }

        $qrContent = $this->totpAuthenticator->getQRContent($user);

        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $qrContent,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return $builder->build()->getDataUri();
    }

    #[LiveAction()]
    public function enableTOTPAuth(): void
    {
        $this->submitForm();

        $data = $this->getForm()->getData();

        $secret = $data['secret'] ?? $this->totpSecret;

        $user = $this->getUser();
        assert($user instanceof UserTwoFactorInterface);

        $user->setTotpSecret($secret);
        if ($user->getBackupCodes() === []) {
            $user->setBackUpCodes($this->generateBackupCodes());
            $this->showBackupCodes = true;
        }

        $this->userRepository->save($user);

        $this->dispatchBrowserEvent('modal:close');
    }

    #[LiveAction()]
    public function disableTOTPAuth(): void
    {
        $user = $this->getUser();
        assert($user instanceof UserTwoFactorInterface);

        $user->setTotpSecret('');

        if (! $user->is2FaEnabled()) {
            $user->setBackUpCodes([]);
        }

        $this->userRepository->save($user);
    }

    #[LiveAction()]
    public function regenerateBackupCodes(): void
    {
        $user = $this->getUser();
        assert($user instanceof UserTwoFactorInterface);

        $user->setBackUpCodes($this->generateBackupCodes());

        $this->userRepository->save($user);
    }

    #[ExposeInTemplate]
    public function isDeviceTrusted(): bool
    {
        $user = $this->getUser();
        assert($user instanceof UserTwoFactorInterface);

        return $this->trustedDeviceManager->isTrustedDevice($user, 'main');
    }

    #[LiveAction()]
    public function clearTrustedDevice(): void
    {
        $user = $this->getUser();
        assert($user instanceof UserInterface);

        $this->trustedDeviceTokenStorage->clearTrustedToken($user->getUserIdentifier(), 'main');
    }

    #[Override]
    /**
     * @return \Symfony\Component\Form\FormInterface<array{code: string|null, secret: string|null}>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(TwoFactorVerifyType::class, [
            'secret' => $this->totpSecret,
        ], [
            'secret' => $this->totpSecret,
        ]);
    }

    /**
     * @return list<string>
     */
    private function generateBackupCodes(int $number = BackupCodeGeneratorInterface::LIMIT): array
    {
        return $this->backupCodeGenerator->generateBackupCodes($number);
    }
}
