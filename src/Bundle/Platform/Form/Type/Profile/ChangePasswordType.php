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

namespace SolidWorx\Platform\PlatformBundle\Form\Type\Profile;

use Override;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicyInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The change-password form: prove you know the current password, then pick a new one twice.
 *
 * The current-password field is what makes the page safe to leave open on a shared machine — a
 * stolen session alone is not enough to lock the real owner out. {@see UserPassword} checks it
 * against the *authenticated* user, never against a user named in the request.
 *
 * The rules the new password has to satisfy come from {@see PasswordPolicyInterface}, so the
 * bullet list rendered on the page and the constraints enforced here are the same list.
 *
 * The form is unmapped: it never touches the user object. Hashing and persisting are the
 * controller's job, which keeps the plain-text password out of the entity entirely.
 *
 * @extends AbstractType<mixed>
 */
final class ChangePasswordType extends AbstractType
{
    /**
     * The field holding the password the user is signing in with today.
     */
    public const string CURRENT_PASSWORD = 'currentPassword';

    /**
     * The repeated field holding the password they want instead.
     */
    public const string NEW_PASSWORD = 'newPassword';

    public function __construct(
        private readonly PasswordPolicyInterface $passwordPolicy,
    ) {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(self::CURRENT_PASSWORD, PasswordType::class, [
                'label' => 'Current password',
                'required' => true,
                'attr' => [
                    'autofocus' => true,
                    'autocomplete' => 'current-password',
                ],
                'constraints' => [
                    new NotBlank(),
                    new UserPassword(message: 'The current password you entered is not correct.'),
                ],
            ])
            ->add(self::NEW_PASSWORD, RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => true,
                'invalid_message' => 'The two passwords do not match.',
                'first_options' => [
                    'label' => 'New password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => 'Repeat new password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'constraints' => $this->passwordPolicy->constraints(),
            ])
        ;

        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->rejectUnchangedPassword(...));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id' => 'solidworx_platform_change_password',
        ]);
    }

    /**
     * Rotating a password onto itself looks like it worked but changes nothing, so say so.
     *
     * It runs on the root form because neither field can see the other one's data on its own.
     */
    private function rejectUnchangedPassword(FormEvent $event): void
    {
        $form = $event->getForm();

        if (! $form->isRoot()) {
            return;
        }

        $current = $form->get(self::CURRENT_PASSWORD)->getData();
        $new = $form->get(self::NEW_PASSWORD)->getData();

        if ($current !== null && $current === $new) {
            $form->get(self::NEW_PASSWORD)->addError(
                new FormError('Your new password has to be different from your current one.')
            );
        }
    }
}
