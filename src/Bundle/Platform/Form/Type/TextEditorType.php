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

namespace SolidWorx\Platform\PlatformBundle\Form\Type;

use Override;
use SolidWorx\Platform\PlatformBundle\Form\DataTransformer\JsonDocumentTransformer;
use SolidWorx\Platform\PlatformBundle\Form\DataTransformer\SanitizeHtmlTransformer;
use SolidWorx\Platform\PlatformBundle\Form\TextEditor\HtmlSanitizerFactory;
use SolidWorx\Platform\PlatformBundle\Form\TextEditor\ToolbarPreset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function is_array;
use function is_string;

/**
 * A Tiptap-powered rich text editor that progressively enhances a standard textarea.
 *
 * The field always renders a real `<textarea>` (so it degrades gracefully without JavaScript) and stores
 * either sanitized HTML (default) or a validated Tiptap JSON document. All output is filtered server-side,
 * which is the security boundary regardless of what the browser submits.
 *
 * @extends AbstractType<mixed>
 */
final class TextEditorType extends AbstractType
{
    public function __construct(
        private readonly HtmlSanitizerFactory $sanitizerFactory,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $preset = $this->resolvePreset($options['toolbar']);

        if ($options['output_format'] === 'json') {
            $builder->addModelTransformer(new JsonDocumentTransformer(
                $preset->allowedNodes(),
                $preset->allowedMarks(),
                $preset->allowedHeadingLevels(),
                $options['json_as_array'] === true,
            ));

            return;
        }

        $sanitizer = $options['sanitizer'];

        if (! $sanitizer instanceof HtmlSanitizerInterface) {
            $allowedTags = $options['allowed_tags'];
            $sanitizer = $this->sanitizerFactory->create(
                is_array($allowedTags) ? array_values(array_filter($allowedTags, is_string(...))) : $preset->allowedTags(),
            );
        }

        $builder->addModelTransformer(new SanitizeHtmlTransformer($sanitizer));
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $preset = $this->resolvePreset($options['toolbar']);

        $view->vars['output_format'] = $options['output_format'];
        $view->vars['toolbar'] = $preset->features();
        $view->vars['editor_placeholder'] = $options['placeholder'] ?? '';
        $view->vars['editor_height'] = $options['editor_height'] ?? '';
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'output_format' => 'html',
            'json_as_array' => false,
            'toolbar' => ToolbarPreset::Default->value,
            'allowed_tags' => null,
            'sanitizer' => null,
            'placeholder' => null,
            'editor_height' => null,
        ]);

        $resolver->setAllowedValues('output_format', ['html', 'json']);
        $resolver->setAllowedValues('toolbar', array_map(static fn (ToolbarPreset $preset): string => $preset->value, ToolbarPreset::cases()));
        $resolver->setAllowedTypes('json_as_array', 'bool');
        $resolver->setAllowedTypes('allowed_tags', ['null', 'string[]']);
        $resolver->setAllowedTypes('sanitizer', ['null', HtmlSanitizerInterface::class]);
        $resolver->setAllowedTypes('placeholder', ['null', 'string']);
        $resolver->setAllowedTypes('editor_height', ['null', 'string']);
    }

    #[Override]
    public function getParent(): string
    {
        return TextareaType::class;
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'text_editor';
    }

    private function resolvePreset(mixed $toolbar): ToolbarPreset
    {
        return ToolbarPreset::from(is_string($toolbar) ? $toolbar : ToolbarPreset::Default->value);
    }
}
