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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Form\Type\Profile;

use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ChangePasswordType;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicy;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Form\ConstraintsOptionExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use function array_keys;
use function iterator_to_array;

#[CoversClass(ChangePasswordType::class)]
#[UsesClass(PasswordPolicy::class)]
#[UsesClass(PasswordStrengthLevel::class)]
#[AllowMockObjectsWithoutExpectations]
final class ChangePasswordTypeTest extends TypeTestCase
{
    public function testItAsksForTheCurrentPasswordAndTheNewOneTwice(): void
    {
        $form = $this->factory->create(ChangePasswordType::class);

        self::assertSame(
            [ChangePasswordType::CURRENT_PASSWORD, ChangePasswordType::NEW_PASSWORD],
            array_keys(iterator_to_array($form)),
        );

        self::assertSame(
            ['first', 'second'],
            array_keys(iterator_to_array($form->get(ChangePasswordType::NEW_PASSWORD))),
        );
    }

    /**
     * Knowing the current password is what keeps a stolen session from locking the owner out, so
     * the constraint that checks it has to be on the field itself.
     */
    public function testTheCurrentPasswordIsCheckedAgainstTheAuthenticatedUser(): void
    {
        $form = $this->factory->create(ChangePasswordType::class);

        $constraints = $form->get(ChangePasswordType::CURRENT_PASSWORD)->getConfig()->getOption('constraints');

        self::assertContains(UserPassword::class, self::classesOf($constraints));
    }

    /**
     * The rules come from the policy rather than being spelled out here, so configuration and
     * enforcement cannot drift apart.
     */
    public function testTheNewPasswordIsValidatedWithThePasswordPolicy(): void
    {
        $form = $this->factory->create(ChangePasswordType::class);

        $classes = self::classesOf($form->get(ChangePasswordType::NEW_PASSWORD)->getConfig()->getOption('constraints'));

        self::assertContains(PasswordStrength::class, $classes);
        self::assertContains(NotCompromisedPassword::class, $classes);
    }

    public function testTwoDifferentNewPasswordsAreRejected(): void
    {
        $form = $this->factory->create(ChangePasswordType::class);

        $form->submit([
            ChangePasswordType::CURRENT_PASSWORD => 'the-current-one',
            ChangePasswordType::NEW_PASSWORD => [
                'first' => 'a-brand-new-password',
                'second' => 'a-different-password',
            ],
        ]);

        self::assertFalse($form->get(ChangePasswordType::NEW_PASSWORD)->isSynchronized());
    }

    /**
     * Rotating a password onto itself would report success while changing nothing.
     */
    public function testReusingTheCurrentPasswordIsRejected(): void
    {
        $form = $this->factory->create(ChangePasswordType::class);

        $form->submit([
            ChangePasswordType::CURRENT_PASSWORD => 'the-current-one',
            ChangePasswordType::NEW_PASSWORD => [
                'first' => 'the-current-one',
                'second' => 'the-current-one',
            ],
        ]);

        self::assertCount(1, $form->get(ChangePasswordType::NEW_PASSWORD)->getErrors());
    }

    public function testAGenuinelyNewPasswordIsAccepted(): void
    {
        $form = $this->factory->create(ChangePasswordType::class);

        $form->submit([
            ChangePasswordType::CURRENT_PASSWORD => 'the-current-one',
            ChangePasswordType::NEW_PASSWORD => [
                'first' => 'a-brand-new-password',
                'second' => 'a-brand-new-password',
            ],
        ]);

        self::assertCount(0, $form->get(ChangePasswordType::NEW_PASSWORD)->getErrors());
        self::assertSame('a-brand-new-password', $form->get(ChangePasswordType::NEW_PASSWORD)->getData());
    }

    /**
     * Nothing about the form touches the user object: the plain-text password never leaves the
     * form, and hashing it is the controller's job.
     */
    public function testItIsNotBoundToTheUser(): void
    {
        $form = $this->factory->create(ChangePasswordType::class);

        self::assertNull($form->getConfig()->getOption('data_class'));
    }

    /**
     * @return list<string>
     */
    private static function classesOf(mixed $constraints): array
    {
        self::assertIsIterable($constraints);

        $classes = [];

        foreach ($constraints as $constraint) {
            self::assertIsObject($constraint);

            $classes[] = $constraint::class;
        }

        return $classes;
    }

    /**
     * @return list<PreloadedExtension>
     */
    #[Override]
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension(
                [new ChangePasswordType(new PasswordPolicy(12, PasswordStrengthLevel::Medium, true))],
                [
                    FormType::class => [new ConstraintsOptionExtension()],
                ],
            ),
        ];
    }
}
