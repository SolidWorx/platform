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
use SolidWorx\Platform\PlatformBundle\Form\Extension\CountryTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;

#[CoversClass(CountryTypeExtension::class)]
#[AllowMockObjectsWithoutExpectations]
final class CountryTypeExtensionTest extends TypeTestCase
{
    public function testItWiresTheCountrySelectControllerByDefault(): void
    {
        $stimulusAttributes = new StimulusHelper(twig: null)->createStimulusAttributes();
        $stimulusAttributes->addController('@solidworx/platform/country-select');

        $view = $this->factory->create(CountryType::class)->createView();

        $this->assertIsArray($view->vars['attr']);
        $this->assertSame($stimulusAttributes->toArray()['data-controller'], $view->vars['attr']['data-controller']);
    }

    public function testItSubmitsTheCountryCode(): void
    {
        $form = $this->factory->create(CountryType::class);
        $form->submit('ZA');

        $this->assertSame('ZA', $form->getData());
    }

    public function testItCanBeDisabled(): void
    {
        $view = $this->factory->create(CountryType::class, options: [
            'searchable' => false,
        ])->createView();

        $this->assertIsArray($view->vars['attr']);
        $this->assertArrayNotHasKey('data-controller', $view->vars['attr']);
    }

    public function testItSkipsExpandedChoices(): void
    {
        $view = $this->factory->create(CountryType::class, options: [
            'expanded' => true,
        ])->createView();

        $this->assertIsArray($view->vars['attr']);
        $this->assertArrayNotHasKey('data-controller', $view->vars['attr']);
    }

    public function testItAppendsToExistingControllers(): void
    {
        $view = $this->factory->create(CountryType::class, options: [
            'attr' => [
                'data-controller' => 'other',
            ],
        ])->createView();

        $this->assertIsArray($view->vars['attr']);
        $this->assertSame('other solidworx--platform--country-select', $view->vars['attr']['data-controller']);
    }

    public function testTheSearchableOptionOnlyAcceptsBooleans(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(CountryType::class, options: [
            'searchable' => 'yes',
        ]);
    }

    /**
     * @return list<FormTypeExtensionInterface<mixed>>
     */
    #[Override]
    protected function getTypeExtensions(): array
    {
        return [new CountryTypeExtension()];
    }
}
