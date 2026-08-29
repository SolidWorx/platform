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

namespace SolidWorx\Platform\PlatformBundle\Form\Type\Tenant;

use Override;
use SolidWorx\Platform\PlatformBundle\Form\Event\TenantOnboardingFormEvent;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use function is_string;

/**
 * The form behind the onboarding screen, creating a user's first tenant.
 *
 * Bound to the configured tenant class rather than the shipped entity, so an application that has
 * customised its tenant gets a form that maps straight onto it. Extra fields are added by listening
 * to {@see TenantOnboardingFormEvent}; the whole type can be swapped out with
 * `platform.multi_tenancy.onboarding.form_type` when that is not enough.
 *
 * @extends AbstractType<TenantInterface>
 */
final class TenantOnboardingType extends AbstractType
{
    /**
     * @param class-string<TenantInterface> $tenantClass
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.models.tenant')]
        private readonly string $tenantClass,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'Workspace name',
            'required' => true,
            'attr' => [
                'autofocus' => true,
                'placeholder' => 'Acme Inc.',
            ],
            'constraints' => [
                new NotBlank(),
                new Length(max: 255),
            ],
        ]);

        $this->eventDispatcher->dispatch(new TenantOnboardingFormEvent($builder, $options));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->tenantClass,
            // The tenant model requires a name in its constructor, so the entity cannot be
            // instantiated before the form is submitted — it is built from the submitted name here
            // instead.
            'empty_data' => function (FormInterface $form): TenantInterface {
                $name = $form->get('name')->getData();

                return new $this->tenantClass(is_string($name) ? $name : '');
            },
        ]);
    }
}
