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

namespace SolidWorx\Platform\PlatformBundle\Form\Event;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched while the onboarding form is being built, so an application can add its own fields to
 * the tenant it has customised.
 *
 * This is the cheap half of the onboarding extension story: adding a "industry" or "team size"
 * field needs a listener, not a replacement form type. Replace the type wholesale via
 * `platform.multi_tenancy.onboarding.form_type` when the whole shape of the form has to change.
 *
 * ```php
 * #[AsEventListener]
 * public function __invoke(TenantOnboardingFormEvent $event): void
 * {
 *     $event->getBuilder()->add('industry', ChoiceType::class, ['choices' => [...]]);
 * }
 * ```
 */
final class TenantOnboardingFormEvent extends Event
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    public function __construct(
        private readonly FormBuilderInterface $builder,
        private readonly array $options,
    ) {
    }

    /**
     * @return FormBuilderInterface<mixed>
     */
    public function getBuilder(): FormBuilderInterface
    {
        return $this->builder;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
