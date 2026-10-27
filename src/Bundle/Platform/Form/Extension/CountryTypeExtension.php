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

namespace SolidWorx\Platform\PlatformBundle\Form\Extension;

use Override;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function is_array;
use function is_string;

/**
 * Turns Symfony's `CountryType` into a searchable tom-select dropdown that shows a Tabler flag next to
 * every country.
 *
 * The choice values are already ISO 3166-1 alpha-2 codes, which is exactly what Tabler's
 * `flag-country-*` classes are keyed on, so the Stimulus controller only needs the `<select>` itself.
 */
final class CountryTypeExtension extends AbstractTypeExtension
{
    /**
     * Stimulus identifier of `@solidworx/platform/country-select`, as `stimulus_controller()` renders it.
     */
    private const string CONTROLLER = 'solidworx--platform--country-select';

    /**
     * @return list<class-string>
     */
    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [CountryType::class];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('searchable', value: true);
        $resolver->setAllowedTypes('searchable', 'bool');
    }

    /**
     * @param FormInterface<mixed> $form
     */
    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Expanded choices render radios/checkboxes, which tom-select cannot enhance.
        if ($options['searchable'] !== true || $options['expanded'] === true) {
            return;
        }

        $attr = is_array($view->vars['attr'] ?? null) ? $view->vars['attr'] : [];
        $existing = $attr['data-controller'] ?? null;
        $attr['data-controller'] = is_string($existing) && $existing !== '' ? $existing . ' ' . self::CONTROLLER : self::CONTROLLER;

        $view->vars['attr'] = $attr;
    }
}
