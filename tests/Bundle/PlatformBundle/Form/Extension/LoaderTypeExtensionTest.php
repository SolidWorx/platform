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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Form\Extension;

use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidWorx\Platform\PlatformBundle\Form\Extension\LoaderTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;

#[CoversClass(LoaderTypeExtension::class)]
#[AllowMockObjectsWithoutExpectations]
final class LoaderTypeExtensionTest extends TypeTestCase
{
    private const string CONTROLLER = 'solidworx--platform--loading';

    public function testItWiresTheLoadingControllerOntoTheRootFormByDefault(): void
    {
        $view = $this->factory->create()->createView();

        self::assertIsArray($view->vars['attr']);
        self::assertSame(self::CONTROLLER, $view->vars['attr']['data-controller']);
        self::assertSame('submit->' . self::CONTROLLER . '#onSubmit', $view->vars['attr']['data-action']);
    }

    /**
     * The extension derives the Stimulus identifier itself instead of going through StimulusBundle, so
     * this pins it to what `stimulus_controller('@solidworx/platform/loading')` renders in a template.
     */
    public function testTheIdentifierMatchesTheOneStimulusBundleGenerates(): void
    {
        $stimulusAttributes = new StimulusHelper(null)->createStimulusAttributes();
        $stimulusAttributes->addController('@solidworx/platform/loading');
        $stimulusAttributes->addAction('@solidworx/platform/loading', 'onSubmit', 'submit');

        $view = $this->factory->create()->createView();

        self::assertIsArray($view->vars['attr']);
        self::assertSame($stimulusAttributes->toArray(), [
            'data-controller' => $view->vars['attr']['data-controller'],
            'data-action' => $view->vars['attr']['data-action'],
        ]);
    }

    public function testItLeavesChildFormsAlone(): void
    {
        $view = $this->factory->createBuilder()
            ->add('name', TextType::class)
            ->getForm()
            ->createView();

        self::assertIsArray($view->children['name']->vars['attr']);
        self::assertArrayNotHasKey('data-controller', $view->children['name']->vars['attr']);
        self::assertArrayNotHasKey('data-action', $view->children['name']->vars['attr']);
    }

    public function testTheLoaderCanBeDisabledPerForm(): void
    {
        $view = $this->factory->create(options: [
            'loader' => false,
        ])->createView();

        self::assertIsArray($view->vars['attr']);
        self::assertArrayNotHasKey('data-controller', $view->vars['attr']);
        self::assertArrayNotHasKey('data-action', $view->vars['attr']);
    }

    public function testItAppendsToControllersTheFormAlreadyDeclares(): void
    {
        $view = $this->factory->create(options: [
            'attr' => [
                'data-controller' => 'other',
                'data-action' => 'click->other#run',
            ],
        ])->createView();

        self::assertIsArray($view->vars['attr']);
        self::assertSame('other ' . self::CONTROLLER, $view->vars['attr']['data-controller']);
        self::assertSame('click->other#run submit->' . self::CONTROLLER . '#onSubmit', $view->vars['attr']['data-action']);
    }

    public function testTheLoaderOptionOnlyAcceptsBooleans(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(options: [
            'loader' => 'yes',
        ]);
    }

    /**
     * @return list<FormTypeExtensionInterface>
     */
    #[Override]
    protected function getTypeExtensions(): array
    {
        return [new LoaderTypeExtension()];
    }
}
