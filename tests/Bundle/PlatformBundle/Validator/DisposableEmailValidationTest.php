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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Validator;

use EmailChecker\Adapter\AggregatorAdapter;
use EmailChecker\Adapter\ArrayAdapter;
use EmailChecker\Adapter\BuiltInAdapter;
use EmailChecker\Constraints\NotThrowawayEmail;
use EmailChecker\Constraints\NotThrowawayEmailValidator;
use EmailChecker\EmailChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Validator\Fixtures\UserFixture;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(User::class)]
final class DisposableEmailValidationTest extends TestCase
{
    /**
     * A concrete subclass inherits the disposable-email constraint automatically
     * through the protected `email` property declared on the abstract base —
     * no per-extender wiring is required.
     */
    public function testConstraintIsInheritedByConcreteSubclasses(): void
    {
        $attributes = new ReflectionProperty(UserFixture::class, 'email')
            ->getAttributes(NotThrowawayEmail::class);

        $this->assertCount(1, $attributes, 'The NotThrowawayEmail constraint must be inherited by concrete User subclasses.');
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function emailProvider(): iterable
    {
        yield 'built-in disposable domain' => ['foo@yopmail.com', 1];
        yield 'custom-list disposable domain' => ['foo@demo-temp.test', 1];
        yield 'subdomain of disposable domain (parent matching)' => ['foo@sub.yopmail.com', 1];
        yield 'normal domain' => ['foo@gmail.com', 0];
    }

    #[DataProvider('emailProvider')]
    public function testDisposableEmailValidation(string $email, int $expectedViolations): void
    {
        $constraint = new NotThrowawayEmail(
            message: 'Disposable or temporary email addresses are not allowed. Please use a permanent email address.',
        );

        $violations = $this->createValidator()->validate($email, $constraint);

        $this->assertCount($expectedViolations, $violations);
    }

    private function createValidator(): ValidatorInterface
    {
        $emailChecker = new EmailChecker(
            new AggregatorAdapter([
                new BuiltInAdapter(),
                new ArrayAdapter(['demo-temp.test']),
            ]),
        );

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory($this->createValidatorFactory($emailChecker))
            ->getValidator();
    }

    private function createValidatorFactory(EmailChecker $emailChecker): ConstraintValidatorFactoryInterface
    {
        return new readonly class($emailChecker) implements ConstraintValidatorFactoryInterface {
            private NotThrowawayEmailValidator $notThrowawayEmailValidator;

            public function __construct(EmailChecker $emailChecker)
            {
                $this->notThrowawayEmailValidator = new NotThrowawayEmailValidator($emailChecker);
            }

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                return $this->notThrowawayEmailValidator;
            }
        };
    }
}
