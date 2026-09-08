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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
 */
#[Route(path: self::PATH, name: self::ROUTE_NAME, methods: ['GET', 'POST'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
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
     * @param class-string<FormTypeInterface> $formType The class configured under `platform.profile.form_type`
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'solidworx_platform.profile.templates.edit')]
        private readonly string $template,
        #[Autowire(param: 'solidworx_platform.profile.form_type')]
        private readonly string $formType,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->currentUser();

        $form = $this->createForm($this->formType, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return new RedirectResponse($this->generateUrl(ShowProfile::ROUTE_NAME))
                ->withFlash(Flash::Success, 'Your profile has been updated.');
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
