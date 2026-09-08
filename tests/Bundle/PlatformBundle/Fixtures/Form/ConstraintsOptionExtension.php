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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Form;

use Override;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Defines the `constraints` option without wiring a validator behind it.
 *
 * `TypeTestCase` builds a bare form factory, so the option a form type declares its constraints
 * through does not exist. Registering Symfony's own ValidatorExtension would define it, but it
 * would also validate on submit — which would need a real user token and a live breach-check API
 * for the constraints these form types use. Defining the option on its own is enough to assert
 * that a type declares the right constraints, and keeps submission tests free of side effects.
 */
final class ConstraintsOptionExtension extends AbstractTypeExtension
{
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
        $resolver->setDefault('constraints', []);
    }
}
