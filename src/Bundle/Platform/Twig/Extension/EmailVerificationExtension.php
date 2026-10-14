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

namespace SolidWorx\Platform\PlatformBundle\Twig\Extension;

use Override;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\EmailVerificationGateInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `is_email_verification_gated()` and `email_verification_message(action)`, the template side of
 * {@see EmailVerificationGateInterface}.
 */
final class EmailVerificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly EmailVerificationGateInterface $gate,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_email_verification_gated', fn (): bool => $this->gate->isGated()),
            new TwigFunction('email_verification_message', $this->gate->reason(...)),
        ];
    }
}
