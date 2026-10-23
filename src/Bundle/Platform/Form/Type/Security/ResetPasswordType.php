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

namespace SolidWorx\Platform\PlatformBundle\Form\Type\Security;

use Override;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicyInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The new password, twice, held to the same {@see PasswordPolicyInterface} as the change-password
 * page — a reset must not become the way around the password rules.
 *
 * Unmapped, like the change-password form: the controller hashes the value and only the hash
 * reaches the user.
 *
 * @extends AbstractType<mixed>
 */
final class ResetPasswordType extends AbstractType
{
    public const string NEW_PASSWORD = 'newPassword';

    public function __construct(
        private readonly PasswordPolicyInterface $passwordPolicy,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(self::NEW_PASSWORD, RepeatedType::class, [
            'type' => PasswordType::class,
            'required' => true,
            'invalid_message' => 'The two passwords do not match.',
            'first_options' => [
                'label' => 'New password',
                'attr' => [
                    'autofocus' => true,
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
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id' => 'solidworx_platform_reset_password',
        ]);
    }
}
