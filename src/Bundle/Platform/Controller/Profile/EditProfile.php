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
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ProfileType;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\EmailVerifier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Edits the signed-in user's own details.
 *
 * The form is bound to {@see self::currentUser()} and nothing else, so the only account this
 * action can ever write to is the one behind the session. The form type is whatever
 * `platform.profile.form_type` names — {@see ProfileType} unless an application replaced it —
 * which is also the boundary that decides which columns are writable: a field the form does not
 * declare cannot be set, no matter what the request body contains.
 *
 * Changing the email address un-verifies the account and mails a link to the new address, so a
 * verified user cannot switch to an address they do not own and keep the verified status.
 */
#[Route(path: self::PATH, name: self::ROUTE_NAME, methods: ['GET', 'POST'])]
#[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
final class EditProfile extends BaseController
{
    /**
     * The path of the edit-profile page.
     */
    public const string PATH = '/profile/edit';

    /**
     * The route name of the edit-profile page.
     */
    public const string ROUTE_NAME = 'solidworx_platform_profile_edit';

    /**
     * @param class-string<FormTypeInterface<mixed>> $formType The class configured under `platform.profile.form_type`
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'solidworx_platform.profile.templates.edit')]
        private readonly string $template,
        #[Autowire(param: 'solidworx_platform.profile.form_type')]
        private readonly string $formType,
        // Not registered while email verification is turned off.
        private readonly ?EmailVerifier $emailVerifier = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->currentUser();

        $originalEmail = $user->getEmail();

        $form = $this->createForm($this->formType, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $emailChanged = $this->emailVerifier instanceof EmailVerifier && $user->getEmail() !== $originalEmail;

            if ($emailChanged) {
                $user->setVerified(verified: false);
            }

            $this->entityManager->flush();

            $response = new RedirectResponse($this->generateUrl(ShowProfile::ROUTE_NAME));

            if (! $emailChanged) {
                return $response->withFlash(Flash::Success, 'Your profile has been updated.');
            }

            try {
                $this->emailVerifier->sendEmailConfirmation($user);
            } catch (TransportExceptionInterface) {
                return $response->withFlash(Flash::Warning, 'Your profile has been updated, but the verification email could not be sent. Please request a new one.');
            }

            return $response->withFlash(Flash::Info, 'Your profile has been updated. We sent a verification link to your new email address.');
        }

        return $this->render(
            $this->template,
            [
                'form' => $form,
                'user' => $user,
            ],
            // A rejected submission is not a successful GET; 422 keeps Turbo and friends from
            // treating the re-rendered form as a new page.
            new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK),
        );
    }
}
