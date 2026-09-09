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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Column;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Override;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Column\DoctrineColumnFactory;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Entity\Client;

#[CoversClass(DoctrineColumnFactory::class)]
final class DoctrineColumnFactoryTest extends TestCase
{
    private DoctrineColumnFactory $factory;

    #[Override]
    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        $this->factory = new DoctrineColumnFactory(new EntityManager($connection, $config));
    }

    public function testMapsEachDoctrineTypeToItsColumn(): void
    {
        $columns = $this->indexByName($this->factory->createForEntity(Client::class));

        self::assertInstanceOf(NumberColumn::class, $columns['id']);
        self::assertInstanceOf(TextColumn::class, $columns['companyName']);
        self::assertInstanceOf(TextColumn::class, $columns['notes']);
        self::assertInstanceOf(BooleanColumn::class, $columns['active']);
        self::assertInstanceOf(NumberColumn::class, $columns['seatCount']);
        self::assertInstanceOf(MoneyColumn::class, $columns['monthlyFee']);
        self::assertInstanceOf(DateColumn::class, $columns['createdAt']);
    }

    public function testHumanisesTitles(): void
    {
        $columns = $this->indexByName($this->factory->createForEntity(Client::class));

        self::assertSame('Company Name', $columns['companyName']->getTitle());
    }

    public function testIdentifierIsPresentButNotSearchable(): void
    {
        $columns = $this->indexByName($this->factory->createForEntity(Client::class));

        self::assertArrayHasKey('id', $columns);
        self::assertFalse($columns['id']->isSearchable());
    }

    public function testFieldWithoutAPublicReaderIsSkipped(): void
    {
        $columns = $this->indexByName($this->factory->createForEntity(Client::class));

        self::assertArrayNotHasKey('internalToken', $columns);
    }

    public function testToOneAssociationWithToStringIsIncluded(): void
    {
        $columns = $this->indexByName($this->factory->createForEntity(Client::class));

        self::assertArrayHasKey('country', $columns);
        self::assertInstanceOf(TextColumn::class, $columns['country']);
        self::assertSame('Country', $columns['country']->getTitle());
    }

    public function testToOneAssociationWithoutToStringIsSkipped(): void
    {
        $columns = $this->indexByName($this->factory->createForEntity(Client::class));

        self::assertArrayNotHasKey('primaryTag', $columns);
    }

    public function testToManyAssociationIsSkipped(): void
    {
        $columns = $this->indexByName($this->factory->createForEntity(Client::class));

        self::assertArrayNotHasKey('tags', $columns);

        // `countries` targets Country, which IS Stringable, so this can only be
        // excluded by the to-many rule -- unlike `tags`, whose target Tag is not
        // Stringable and would be excluded by that gate on its own.
        self::assertArrayNotHasKey('countries', $columns);
    }

    /**
     * @param list<ColumnInterface> $columns
     *
     * @return array<string, ColumnInterface>
     */
    private function indexByName(array $columns): array
    {
        $indexed = [];

        foreach ($columns as $column) {
            $indexed[$column->getName()] = $column;
        }

        return $indexed;
    }
}
