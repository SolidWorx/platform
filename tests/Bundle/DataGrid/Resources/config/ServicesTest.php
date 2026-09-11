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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Resources\config;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\CsrfTokenManagerPass;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\DataTableRegistryPass;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridRenderer;
use SolidWorx\Platform\DataGridBundle\Twig\DataGridTwigExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;
use function array_keys;
use function array_map;
use function dirname;

/**
 * Compiles a real container from BOTH upstream `pentiminax/ux-datatables`'s
 * `config/services.php` AND this bundle's own `Resources/config/services.php`
 * -- the two files a real application ends up with once both bundles are
 * registered -- rather than stubbing {@see DataTablesExtension} directly the
 * way every other test in this suite does.
 *
 * That stubbing is exactly why the bug this class guards against shipped
 * unnoticed: upstream registers {@see DataTablesExtension} only under the
 * service id `datatables.twig_extension`, with no class alias, so autowiring
 * a constructor argument typed against the class itself -- which is what
 * {@see DataGridRenderer}'s constructor did before this class's wiring fixed
 * it -- cannot resolve, and a real application's container fails to compile.
 * No test that hands `DataGridRenderer` a stub extension directly can ever
 * observe that.
 *
 * Framework services upstream's config depends on but does not itself
 * provide are stubbed below, as real functioning instances wherever that is
 * cheap (`request_stack`, `stimulus.helper`, `security.csrf.token_manager`,
 * `twig`, `property_accessor`). The one corner not covered:
 * {@see EntityManagerInterface} is registered `synthetic` rather than backed
 * by a real Doctrine ORM instance --
 * {@see \SolidWorx\Platform\DataGridBundle\Column\DoctrineColumnFactory}
 * needs it autowired for the container to compile, but nothing this class
 * asserts on ever calls `$container->get()` for it, and standing up a real
 * EntityManager (connection, metadata driver, mapping) to satisfy a service
 * this test never touches is out of proportion to what it is proving.
 */
#[CoversClass(DataGridRenderer::class)]
#[CoversClass(DataGridTwigExtension::class)]
final class ServicesTest extends TestCase
{
    private static ContainerBuilder $container;

    public static function setUpBeforeClass(): void
    {
        $container = new ContainerBuilder();
        self::registerFrameworkStubs($container);

        $container->addCompilerPass(new DataTableRegistryPass());
        $container->addCompilerPass(new CsrfTokenManagerPass());

        $upstreamLoader = new PhpFileLoader($container, new FileLocator(self::upstreamConfigDir()));
        $upstreamLoader->load('services.php');

        $ourLoader = new PhpFileLoader($container, new FileLocator(self::ourConfigDir()));
        $ourLoader->load('services.php');

        // Public only so this test can assert on them directly; nothing in
        // the wiring itself requires it.
        $container->getDefinition(DataGridRenderer::class)->setPublic(true);
        $container->getDefinition(DataGridTwigExtension::class)->setPublic(true);

        $container->compile();

        self::$container = $container;
    }

    /**
     * The exact precondition Part 1 fixes. Documented as its own assertion,
     * against a fresh, unmerged container, so a future upstream release that
     * starts aliasing the class fails this test loudly rather than leaving
     * the fix silently unnecessary.
     */
    public function testUpstreamRegistersDataTablesExtensionOnlyByServiceIdWithNoClassAlias(): void
    {
        $container = new ContainerBuilder();

        $loader = new PhpFileLoader($container, new FileLocator(self::upstreamConfigDir()));
        $loader->load('services.php');

        self::assertFalse($container->has(DataTablesExtension::class));
        self::assertTrue($container->getDefinition('datatables.twig_extension')->hasTag('twig.extension'));
    }

    public function testDataGridRendererIsConstructible(): void
    {
        self::assertInstanceOf(DataGridRenderer::class, self::$container->get(DataGridRenderer::class));
    }

    public function testDecoratorIsConstructibleAndReceivesTheRealUpstreamExtensionNotItself(): void
    {
        $decorator = self::$container->get(DataGridTwigExtension::class);
        self::assertInstanceOf(DataGridTwigExtension::class, $decorator);

        $renderer = self::$container->get(DataGridRenderer::class);
        self::assertInstanceOf(DataGridRenderer::class, $renderer);

        $property = new ReflectionProperty(DataGridRenderer::class, 'dataTables');
        $dataTables = $property->getValue($renderer);

        // The real upstream instance DecoratorServicePass wired in as
        // "<decorator>.inner" -- not our decorator, not a stub -- proving the
        // wiring is not circular. DataTablesExtension is not final, so this
        // is a genuine runtime check, not one PHPStan can already prove from
        // the instanceof above.
        self::assertInstanceOf(DataTablesExtension::class, $dataTables);
        self::assertSame(DataTablesExtension::class, $dataTables::class);
    }

    public function testDecoratingMovesTheTwigExtensionTagSoThereIsNoDuplicateRenderDatatableFunction(): void
    {
        $tagged = self::$container->findTaggedServiceIds('twig.extension');

        // Exactly one service carries the tag post-compile: our decorator.
        // Upstream's original datatables.twig_extension definition lost it
        // -- DecoratorServicePass moved it -- so Twig would never end up with
        // two extensions each declaring render_datatable().
        self::assertSame([DataGridTwigExtension::class], array_keys($tagged));

        $decorator = self::$container->get(DataGridTwigExtension::class);
        self::assertInstanceOf(DataGridTwigExtension::class, $decorator);

        /** @var list<TwigFunction> $functions */
        $functions = $decorator->getFunctions();
        $functionNames = array_map(static fn (TwigFunction $function): string => $function->getName(), $functions);

        self::assertSame(['render_datatable'], $functionNames);
    }

    private static function registerFrameworkStubs(ContainerBuilder $container): void
    {
        $container->setParameter('kernel.secret', 'test-secret');

        $container->setParameter('solidworx_platform_datagrid.page_length', 25);
        $container->setParameter('solidworx_platform_datagrid.length_menu', [10, 25, 50, 100]);
        $container->setParameter('solidworx_platform_datagrid.responsive', true);
        $container->setParameter('solidworx_platform_datagrid.column_control', true);
        $container->setParameter('solidworx_platform_datagrid.table_class', 'table');
        $container->setParameter('solidworx_platform_datagrid.security.ajax_access', 'IS_AUTHENTICATED_FULLY');
        $container->setParameter('solidworx_platform_datagrid.export.enabled', true);
        $container->setParameter('solidworx_platform_datagrid.export.formats', ['csv', 'xlsx']);

        // Back DataTableInfrastructure's %param% placeholders directly,
        // rather than via DataTablesBundle::configure()'s config tree: this
        // test deliberately never boots that bundle class.
        $container->setParameter('datatables.options', []);
        $container->setParameter('datatables.template_parameters', []);
        $container->setParameter('datatables.extensions', []);

        $container->setDefinition('request_stack', new Definition(RequestStack::class));
        $container->setDefinition('stimulus.helper', new Definition(StimulusHelper::class, [null]));

        $container->register('twig', Environment::class)
            ->setArguments([new Definition(ArrayLoader::class)]);

        $container->register('security.csrf.token_manager', CsrfTokenManager::class)
            ->setArguments([
                new Definition(UriSafeTokenGenerator::class),
                new Definition(SessionTokenStorage::class, [new Reference('request_stack')]),
            ]);

        $container->register('property_accessor', PropertyAccessor::class)
            ->setFactory([PropertyAccess::class, 'createPropertyAccessor']);

        // Not backed by a real Doctrine ORM instance -- see class docblock.
        $container->register(EntityManagerInterface::class, EntityManagerInterface::class)
            ->setSynthetic(true);
    }

    private static function upstreamConfigDir(): string
    {
        return dirname(__DIR__, 5) . '/vendor/pentiminax/ux-datatables/config';
    }

    private static function ourConfigDir(): string
    {
        return dirname(__DIR__, 5) . '/src/Bundle/DataGrid/Resources/config';
    }
}
