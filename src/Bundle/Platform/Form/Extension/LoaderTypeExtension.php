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
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Wires the `loading` Stimulus controller onto every form, so that a spinner overlay covers the form
 * while it is being submitted.
 *
 * The `loader` option is available on every form type, but the attributes are only ever written to the
 * root form: child forms render inside the root `<form>` element, so wiring them up as well would stack
 * a controller instance — and an overlay — on every nested widget.
 */
final class LoaderTypeExtension extends AbstractTypeExtension
{
    /**
     * The UX package name of the controller, as declared in `assets/package.json`.
     */
    private const string CONTROLLER_PACKAGE = '@solidworx/platform/loading';

    /**
     * @return list<class-string>
     */
    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('loader', true);
        $resolver->setAllowedTypes('loader', 'bool');
    }

    /**
     * @param FormInterface<mixed> $form
     */
    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if ($options['loader'] !== true || ! $form->isRoot()) {
            return;
        }

        $controller = $this->controllerIdentifier();

        $attr = is_array($view->vars['attr'] ?? null) ? $view->vars['attr'] : [];

        // A form is free to declare its own controllers and actions, so append rather than overwrite.
        $attr['data-controller'] = $this->append($attr['data-controller'] ?? null, $controller);
        $attr['data-action'] = $this->append($attr['data-action'] ?? null, sprintf('submit->%s#onSubmit', $controller));

        $view->vars['attr'] = $attr;
    }

    /**
     * Turns a UX package name into the identifier Stimulus registers it under, the same way
     * `stimulus_controller()` does in Twig.
     *
     * @see \Symfony\UX\StimulusBundle\Dto\StimulusAttributes::normalizeControllerName()
     */
    private function controllerIdentifier(): string
    {
        return str_replace(['/', '_'], ['--', '-'], ltrim(self::CONTROLLER_PACKAGE, '@'));
    }

    private function append(mixed $existing, string $value): string
    {
        return is_string($existing) && $existing !== '' ? $existing . ' ' . $value : $value;
    }
}
