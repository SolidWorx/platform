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

namespace SolidWorx\Platform\PlatformBundle\Security\EmailVerification;

use Override;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EmailVerificationGate implements EmailVerificationGateInterface
{
    public function __construct(
        private Security $security,
        private TranslatorInterface $translator,
        #[Autowire(param: 'solidworx_platform.security.email_verification.enabled')]
        private bool $enabled,
    ) {
    }

    #[Override]
    public function isGated(?UserInterface $user = null): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $user ??= $this->security->getUser();

        // Anything that is not a platform user (an API client, an in-memory admin) has no email
        // address to verify.
        return $user instanceof UserInterface && ! $user->isVerified();
    }

    #[Override]
    public function reason(string $action): string
    {
        return $this->translator->trans('Please verify your email address before %action%.', [
            '%action%' => $action,
        ]);
    }
}
