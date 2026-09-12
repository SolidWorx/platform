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

namespace SolidWorx\Platform\PlatformBundle\Controller\Profile;

use Doctrine\ORM\EntityManagerInterface;
use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use SolidWorx\Platform\PlatformBundle\Enum\Flash;
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ChangePasswordType;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicyInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Webmozart\Assert\Assert;

/**
 * The page a user rotates their own password on.
 *
 * It is deliberately separate from the profile form. A password change is a different kind of
 * act from correcting a phone number: it needs the current password, it should not be mixed into
 * a form somebody opened to fix a typo, and it ends with a new session id.
 *
 * The four things that make it safe:
 *
 * - the account is {@see self::currentUser()}, never an identifier from the request;
 * - {@see ChangePasswordType} requires the current password, so a hijacked session cannot lock
 *   the owner out;
 * - the new password is hashed with the configured hasher and only the hash reaches the entity;
 * - the session id is rotated on success, so a session cookie captured before the change stops
 *   working.
 */
#[Route(path: self::PATH, name: self::ROUTE_NAME, methods: ['GET', 'POST'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ChangePassword extends BaseController
{
    /**
     * The path of the change-password page.
     */
    public const string PATH = '/profile/password';

    /**
     * The route name of the change-password page, linked to from the profile page.
     */
    public const string ROUTE_NAME = 'solidworx_platform_profile_change_password';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordPolicyInterface $passwordPolicy,
        #[Autowire(param: 'solidworx_platform.profile.templates.change_password')]
        private readonly string $template,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->currentUser();

        $form = $this->createForm(ChangePasswordType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get(ChangePasswordType::NEW_PASSWORD)->getData();

            Assert::stringNotEmpty($newPassword);

            $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

            $this->entityManager->flush();

            $this->rotateSession($request);

            return new RedirectResponse($this->generateUrl(ShowProfile::ROUTE_NAME))
                ->withFlash(Flash::Success, 'Your password has been changed.');
        }

        return $this->render(
            $this->template,
            [
                'form' => $form,
                'user' => $user,
                'password_requirements' => $this->passwordPolicy->requirements(),
            ],
            new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK),
        );
    }

    /**
     * Issues a new session id and destroys the old one, keeping the user signed in here while
     * invalidating a session cookie that leaked before the password changed.
     */
    private function rotateSession(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if (! $session->isStarted()) {
            return;
        }

        $session->migrate(true);
    }
}
