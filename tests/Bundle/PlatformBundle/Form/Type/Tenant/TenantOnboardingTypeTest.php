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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Form\Type\Tenant;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use SolidWorx\Platform\PlatformBundle\Entity\Tenant;
use SolidWorx\Platform\PlatformBundle\Form\Event\TenantOnboardingFormEvent;
use SolidWorx\Platform\PlatformBundle\Form\Type\Tenant\TenantOnboardingType;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[CoversClass(TenantOnboardingType::class)]
#[CoversClass(TenantOnboardingFormEvent::class)]
#[UsesClass(\SolidWorx\Platform\PlatformBundle\Model\Tenant::class)]
// TypeTestCase builds its own unconfigured EventDispatcherInterface mock in setUp(); the type under
// test uses the real dispatcher created here instead.
#[AllowMockObjectsWithoutExpectations]
final class TenantOnboardingTypeTest extends TypeTestCase
{
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();

        parent::setUp();
    }

    /**
     * The tenant model requires a name in its constructor, so it cannot exist before the form is
     * submitted — the type has to build it from the submitted data.
     */
    public function testBuildsTheTenantFromTheSubmittedName(): void
    {
        $form = $this->factory->create(TenantOnboardingType::class);

        $form->submit([
            'name' => 'Acme Inc.',
        ]);

        $tenant = $form->getData();

        $this->assertTrue($form->isSynchronized());
        $this->assertInstanceOf(TenantInterface::class, $tenant);
        $this->assertSame('Acme Inc.', $tenant->getName());
    }

    public function testUpdatesAnExistingTenant(): void
    {
        $form = $this->factory->create(TenantOnboardingType::class, new Tenant('Old name'));

        $form->submit([
            'name' => 'New name',
        ]);

        $tenant = $form->getData();

        $this->assertInstanceOf(TenantInterface::class, $tenant);
        $this->assertSame('New name', $tenant->getName());
    }

    /**
     * The cheap extension path: adding a field should not require replacing the type.
     */
    public function testAnApplicationCanAddItsOwnFields(): void
    {
        $this->eventDispatcher->addListener(
            TenantOnboardingFormEvent::class,
            static function (TenantOnboardingFormEvent $event): void {
                $event->getBuilder()->add('industry', TextType::class, [
                    'mapped' => false,
                ]);
            },
        );

        $form = $this->factory->create(TenantOnboardingType::class);

        $this->assertTrue($form->has('industry'));

        $form->submit([
            'name' => 'Acme',
            'industry' => 'Anvils',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('Anvils', $form->get('industry')->getData());
    }

    public function testTheEventCarriesTheResolvedOptions(): void
    {
        $seen = null;

        $this->eventDispatcher->addListener(
            TenantOnboardingFormEvent::class,
            static function (TenantOnboardingFormEvent $event) use (&$seen): void {
                $seen = $event->getOptions();
            },
        );

        $this->factory->create(TenantOnboardingType::class);

        $this->assertIsArray($seen);
        $this->assertSame(Tenant::class, $seen['data_class']);
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([
                new TenantOnboardingType($this->dispatcher(), Tenant::class),
            ], []),
            // The type declares constraints on its fields, which is only a valid option once the
            // validator extension is present.
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }
}
