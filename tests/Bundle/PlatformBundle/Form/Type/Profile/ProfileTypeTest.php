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
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ProfileType;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Form\ConstraintsOptionExtension;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\ProfileUser;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use function array_keys;
use function iterator_to_array;

#[CoversClass(ProfileType::class)]
#[AllowMockObjectsWithoutExpectations]
final class ProfileTypeTest extends TypeTestCase
{
    public function testItExposesTheFieldsEveryPlatformUserHas(): void
    {
        $form = $this->factory->create(ProfileType::class, new ProfileUser());

        self::assertSame(
            ['firstName', 'lastName', 'email', 'mobile'],
            array_keys(iterator_to_array($form)),
        );
    }

    public function testItWritesTheSubmittedDetailsOntoTheUser(): void
    {
        $user = new ProfileUser();
        $user->setEmail('old@example.com');

        $form = $this->factory->create(ProfileType::class, $user);

        $form->submit([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ada@example.com',
            'mobile' => '+27 82 000 0000',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('Ada', $user->getFirstName());
        self::assertSame('Lovelace', $user->getLastName());
        self::assertSame('ada@example.com', $user->getEmail());
        self::assertSame('+27 82 000 0000', $user->getMobile());
    }

    /**
     * The mobile number is optional, so clearing the field has to be allowed to reach the setter.
     */
    public function testTheMobileNumberCanBeCleared(): void
    {
        $user = new ProfileUser();
        $user->setEmail('ada@example.com');
        $user->setMobile('+27 82 000 0000');

        $form = $this->factory->create(ProfileType::class, $user);

        $form->submit([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ada@example.com',
            'mobile' => '',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertNull($user->getMobile());
    }

    /**
     * The form is the boundary that decides which columns a user can write to. Roles, the enabled
     * flag and the password hash are not fields, so a crafted request cannot set them.
     */
    public function testItRefusesToWriteFieldsItDoesNotDeclare(): void
    {
        $user = new ProfileUser();
        $user->setEmail('ada@example.com');
        $user->setPassword('hashed');

        $form = $this->factory->create(ProfileType::class, $user);

        $form->submit([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ada@example.com',
            'mobile' => '',
            'roles' => ['ROLE_ADMIN'],
            'enabled' => '1',
            'password' => 'chosen-by-the-attacker',
        ]);

        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertFalse($user->isEnabled());
        self::assertSame('hashed', $user->getPassword());
    }

    public function testItIsBoundToTheConfiguredUserClass(): void
    {
        $form = $this->factory->create(ProfileType::class, new ProfileUser());

        self::assertSame(ProfileUser::class, $form->getConfig()->getOption('data_class'));
    }

    /**
     * Taking somebody else's address would take their sign-in identifier with it, so the
     * uniqueness check is part of the form rather than left to the database index.
     */
    public function testItRejectsAnEmailAddressAnotherAccountAlreadyUses(): void
    {
        $form = $this->factory->create(ProfileType::class, new ProfileUser());

        $constraints = $form->getConfig()->getOption('constraints');

        self::assertIsArray($constraints);
        self::assertCount(1, $constraints);

        $constraint = $constraints[0];

        self::assertInstanceOf(UniqueEntity::class, $constraint);
        self::assertSame(['email'], $constraint->fields);
        self::assertSame(ProfileUser::class, $constraint->entityClass);
        self::assertSame('email', $constraint->errorPath);
    }

    /**
     * @return list<PreloadedExtension>
     */
    #[Override]
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension(
                [new ProfileType(ProfileUser::class)],
                [
                    FormType::class => [new ConstraintsOptionExtension()],
                ],
            ),
        ];
    }
}
