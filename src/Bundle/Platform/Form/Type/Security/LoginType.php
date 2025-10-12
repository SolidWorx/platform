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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{code: string|null}>
 */
final class LoginType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add($options['username_parameter'], EmailType::class, [
                'label' => 'Email address',
                'placeholder' => 'your@email.com',
                'required' => true,
                'attr' => [
                    'autofocus' => true,
                    'autocomplete' => 'username',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Email(
                        mode: Email::VALIDATION_MODE_STRICT,
                    ),
                ],
            ]);

        $builder->add($options['password_parameter'], PasswordType::class, [
            'label' => 'Password',
            'required' => true,
            'attr' => [
                'autocomplete' => 'current-password',
            ],
            'constraints' => [
                new NotBlank(),
            ],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('username_parameter');
        $resolver->setRequired('password_parameter');
        $resolver->setAllowedTypes('username_parameter', 'string');
        $resolver->setAllowedTypes('password_parameter', 'string');
    }
}
