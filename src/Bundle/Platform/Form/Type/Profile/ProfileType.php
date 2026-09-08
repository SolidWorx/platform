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
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form behind "Edit profile", covering the fields every platform user has.
 *
 * There are two ways to change it, and which one fits depends on how much you are changing:
 *
 * 1. **Add to it.** A custom user class usually only adds a handful of columns, so a form type
 *    extension keeps the platform fields (and this validation) and appends yours:
 *
 *        final class ProfileTypeExtension extends AbstractTypeExtension
 *        {
 *            public static function getExtendedTypes(): iterable
 *            {
 *                return [ProfileType::class];
 *            }
 *
 *            public function buildForm(FormBuilderInterface $builder, array $options): void
 *            {
 *                $builder->add('jobTitle', TextType::class, ['required' => false]);
 *            }
 *        }
 *
 * 2. **Replace it.** Point `platform.profile.form_type` at your own type when the shape of the
 *    form itself is different. It is built with the signed-in user as its data, so it only has
 *    to set `data_class` to your user class — remember to carry over the unique-email constraint
 *    below, which is what stops one user from taking another's address.
 *
 * The password is deliberately absent: it is changed on its own page, behind the current
 * password. Nothing here can grant a role or enable an account either — those columns are not
 * exposed, so a crafted request cannot set them.
 *
 * @extends AbstractType<UserInterface>
 */
final class ProfileType extends AbstractType
{
    /**
     * @param class-string $userClass The class configured under `platform.models.user`
     */
    public function __construct(
        #[Autowire(param: 'solidworx_platform.models.user')]
        private readonly string $userClass,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First name',
                'required' => true,
                'attr' => [
                    'autofocus' => true,
                    'autocomplete' => 'given-name',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last name',
                'required' => true,
                'attr' => [
                    'autocomplete' => 'family-name',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email address',
                'required' => true,
                'help' => 'This is also the address you sign in with.',
                'attr' => [
                    'autocomplete' => 'email',
                ],
            ])
            ->add('mobile', TelType::class, [
                'label' => 'Mobile number',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'tel',
                ],
            ])
        ;
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->userClass,
            // A class constraint on the form's own data, so a taken address fails validation and
            // is reported on the email field instead of blowing up on the unique index at flush.
            'constraints' => [
                new UniqueEntity(
                    fields: ['email'],
                    message: 'An account with this email address already exists.',
                    entityClass: $this->userClass,
                    errorPath: 'email',
                ),
            ],
        ]);
    }
}
