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

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use SolidWorx\Platform\PlatformBundle\Exception\UntrustedLinkHostException;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends the verification link to every new, unverified user as soon as they are saved.
 *
 * A failed send is logged rather than thrown: the account already exists, and the user can ask
 * for another link from the banner or their profile. So is a host that nothing restricts — no
 * link is sent then, and signing up keeps working while the configuration is fixed.
 */
#[AsDoctrineListener(event: Events::postPersist)]
final readonly class SendVerificationEmailListener
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private LoggerInterface $logger,
        #[Autowire(param: 'solidworx_platform.security.email_verification.enabled')]
        private bool $enabled,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $user = $args->getObject();

        if (! $this->enabled || ! $user instanceof UserInterface || $user->isVerified()) {
            return;
        }

        try {
            $this->emailVerifier->sendEmailConfirmation($user);
        } catch (TransportExceptionInterface $transportException) {
            $this->logger->error('Failed to send email confirmation', [
                'exception' => $transportException,
            ]);
        } catch (UntrustedLinkHostException $untrustedLinkHostException) {
            $this->logger->critical('Email confirmation not sent: the link would point at an unchecked host.', [
                'exception' => $untrustedLinkHostException,
            ]);
        }
    }
}
