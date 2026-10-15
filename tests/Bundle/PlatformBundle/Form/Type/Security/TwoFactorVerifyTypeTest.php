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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Form\Type\Security;

use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidWorx\Platform\PlatformBundle\Form\Type\Security\TwoFactorVerifyType;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Form\ConstraintsOptionExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

#[CoversClass(TwoFactorVerifyType::class)]
#[AllowMockObjectsWithoutExpectations]
final class TwoFactorVerifyTypeTest extends TypeTestCase
{
    public function testACodeStartingWithZeroKeepsTheZero(): void
    {
        $form = $this->factory->create(TwoFactorVerifyType::class, null, [
            'secret' => 'SECRET',
        ]);

        $form->submit([
            'code' => '012345',
            'secret' => 'SECRET',
        ]);

        self::assertSame('012345', $form->get('code')->getData());
    }

    #[Override]
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension(
                [new TwoFactorVerifyType()],
                [
                    FormType::class => [new ConstraintsOptionExtension()],
                ],
            ),
        ];
    }
}
