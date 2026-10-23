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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Asks for the email address of the account whose password was forgotten.
 *
 * @extends AbstractType<mixed>
 */
final class ResetPasswordRequestType extends AbstractType
{
    public const string EMAIL = 'email';

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(self::EMAIL, EmailType::class, [
            'label' => 'Email address',
            'attr' => [
                'autofocus' => true,
                'autocomplete' => 'email',
                'placeholder' => 'your@email.com',
            ],
            'constraints' => [
                new NotBlank(message: 'Please enter your email address.'),
                new Email(),
            ],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id' => 'solidworx_platform_reset_password_request',
        ]);
    }
}
