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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use League\Uri\Uri;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Doctrine\Type\URLType;

#[CoversClass(URLType::class)]
final class URLTypeTest extends TestCase
{
    private URLType $type;

    private AbstractPlatform $platform;

    #[Override]
    protected function setUp(): void
    {
        $this->type = new URLType();
        $this->platform = self::createStub(AbstractPlatform::class);
    }

    public function testConvertToDatabaseValueWithUri(): void
    {
        $uri = Uri::new('https://example.com/path?query=1#fragment');

        self::assertSame('https://example.com/path?query=1#fragment', $this->type->convertToDatabaseValue($uri, $this->platform));
    }

    public function testConvertToDatabaseValueWithNull(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertToDatabaseValueThrowsOnInvalidType(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue(12345, $this->platform);
    }

    public function testConvertToPHPValueWithString(): void
    {
        $result = $this->type->convertToPHPValue('https://solidworx.co', $this->platform);

        self::assertInstanceOf(Uri::class, $result);
        self::assertSame('https://solidworx.co', (string) $result);
    }

    public function testConvertToPHPValueWithNull(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertToPHPValueWithUriPassthrough(): void
    {
        $uri = Uri::new('https://example.com');
        $result = $this->type->convertToPHPValue($uri, $this->platform);

        self::assertSame($uri, $result);
    }

    public function testConvertToPHPValueThrowsOnInvalidType(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToPHPValue(42, $this->platform);
    }
}
