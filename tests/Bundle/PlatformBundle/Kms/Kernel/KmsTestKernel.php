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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Kms\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Knp\Bundle\MenuBundle\KnpMenuBundle;
use Override;
use SolidWorx\Platform\PlatformBundle\SolidWorxPlatformBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\UX\Icons\UXIconsBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;
use function dirname;
use function md5;
use function serialize;
use function sys_get_temp_dir;

/**
 * A real kernel with key management and the platform, to prove the wiring a compiled container gets.
 */
final class KmsTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * The classes of the services the stored encrypter is made of, outermost first.
     */
    public const string CHAIN = 'test.kms.stored_envelope_encrypter_chain';

    /**
     * @param array<string, mixed> $platform The raw `platform:` section
     * @param array<string, mixed>|null $keyManagement The application's own key_management config
     */
    public function __construct(
        private readonly array $platform,
        private readonly ?array $keyManagement = null,
    ) {
        parent::__construct('test', debug: false);
    }

    /**
     * @return iterable<Bundle>
     */
    #[Override]
    public function registerBundles(): iterable
    {
        // FrameworkBundle 8.2 registers KeyManagementBundle.
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new KnpMenuBundle();
        yield new UXIconsBundle();
        yield new DoctrineBundle();

        $platform = new SolidWorxPlatformBundle();
        // Features whose bundles this kernel leaves out are off.
        $platform->setPlatformRawConfig($this->platform + [
            'security' => [
                'email_verification' => [
                    'enabled' => false,
                ],
                'two_factor' => [
                    'enabled' => false,
                ],
            ],
        ]);

        yield $platform;
    }

    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        // Read the compiled definitions rather than the service: building it opens the DBAL
        // connection of the store, and the beta encrypted types reject DoctrineBundle's per-connection
        // type provider, so instantiating it fails for reasons unrelated to this wiring.
        $container->addCompilerPass(new class(self::CHAIN) implements CompilerPassInterface {
            public function __construct(
                private readonly string $parameter,
            ) {
            }

            public function process(ContainerBuilder $container): void
            {
                $chain = [];
                $definition = $container->findDefinition($this->parameter);

                while ($definition instanceof Definition) {
                    $chain[] = (string) $definition->getClass();
                    $inner = $definition->getClass() === StoredEnvelopeEncrypter::class ? null : ($definition->getArguments()[0] ?? null);
                    $definition = $inner instanceof Reference ? $container->findDefinition((string) $inner) : $inner;
                }

                $container->removeAlias($this->parameter);
                $container->setParameter($this->parameter, $chain);
            }
        }, PassConfig::TYPE_AFTER_REMOVING);
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_kms_kernel_test/' . md5(serialize([$this->platform, $this->keyManagement])) . '/cache';
    }

    #[Override]
    public function getBuildDir(): string
    {
        return $this->getCacheDir();
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/solidworx_kms_kernel_test/log';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 5);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'kms-kernel-test',
            'test' => true,
            'http_method_override' => false,
            'csrf_protection' => true,
            'form' => true,
            'session' => [
                'storage_factory_id' => 'session.storage.factory.mock_file',
            ],
        ]);

        $container->extension('security', [
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                ],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'url' => 'sqlite:///:memory:',
            ],
            'orm' => [
                'mappings' => [
                    'KmsFixtures' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => dirname(__DIR__, 2) . '/Fixtures/Kms/Entity',
                        'prefix' => 'SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\Entity',
                    ],
                ],
            ],
        ]);

        // Set by the UiBundle, which this kernel leaves out.
        $parameters = $container->parameters();
        foreach (['login', 'tenant_no_access', 'tenant_onboarding', 'tenant_select'] as $template) {
            $parameters->set('solidworx_platform_ui.template.' . $template, $template . '.html.twig');
        }

        if ($this->keyManagement !== null) {
            $container->extension('key_management', $this->keyManagement);
        }

        $services = $container->services()
            ->defaults()
            ->autoconfigure();

        // As an application loads src/: entities become resources, which is how #[Encrypted] columns are found.
        // Keeps the encrypter from being inlined before the chain is read.
        $services->alias(self::CHAIN, 'key_management.stored_envelope_encrypter')
            ->public();

        $services->load('SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\Entity\\', dirname(__DIR__, 2) . '/Fixtures/Kms/Entity');
    }
}
