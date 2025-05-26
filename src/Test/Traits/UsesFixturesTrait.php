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

namespace SolidWorx\Platform\Test\Traits;

use const DIRECTORY_SEPARATOR;
use JsonException;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use ReflectionClass;
use RuntimeException;
use function array_slice;
use function dirname;
use function implode;
use function json_decode;

trait UsesFixturesTrait
{
    private static string $fixturesPath = '';

    #[BeforeClass]
    public static function loadFixturesPath(): void
    {
        static::$fixturesPath = static::getFixturesPath();
    }

    #[AfterClass]
    public static function clearFixturesPath(): void
    {
        static::$fixturesPath = '';
    }

    protected static function getFixturesPath(string $path = ''): string
    {
        if (static::$fixturesPath !== '') {
            return static::$fixturesPath . ($path !== '' ? '/' . $path : '');
        }

        $ref = new ReflectionClass(static::class);

        $fileName = $ref->getFileName();
        if ($fileName === false) {
            throw new RuntimeException('Could not determine the file name of the test class.');
        }

        $topLevelDir = dirname($fileName);

        $parts = explode(DIRECTORY_SEPARATOR, $topLevelDir);

        $counter = count($parts) - 1;

        do {
            $lastPart = $parts[$counter];
            if ($lastPart === 'tests') {
                break;
            }

            $topLevelDir = dirname($topLevelDir);
            $counter--;
        } while ($counter > 0);

        $dirs = array_slice($parts, 0, $counter + 3);

        static::$fixturesPath = implode(DIRECTORY_SEPARATOR, $dirs) . '/fixtures';

        return static::$fixturesPath . ($path !== '' ? '/' . $path : '');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    protected static function loadFixture(string $path): array
    {
        $fixturePath = static::getFixturesPath($path);

        if (! file_exists($fixturePath)) {
            throw new RuntimeException(sprintf('Fixture file "%s" does not exist.', $fixturePath));
        }

        $content = file_get_contents($fixturePath);
        if ($content === false) {
            throw new RuntimeException(sprintf('Could not read fixture file "%s".', $fixturePath));
        }

        return (array) json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }
}
