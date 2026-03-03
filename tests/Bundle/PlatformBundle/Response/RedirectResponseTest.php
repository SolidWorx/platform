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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Enum\Flash;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse as BaseRedirectResponse;
use function is_a;

#[CoversClass(RedirectResponse::class)]
final class RedirectResponseTest extends TestCase
{
    public function testExtendsBaseRedirectResponse(): void
    {
        self::assertTrue(is_a(RedirectResponse::class, BaseRedirectResponse::class, true));
    }

    public function testStartsWithNoFlashes(): void
    {
        $response = new RedirectResponse('/some-url');

        self::assertSame([], $response->getFlashes());
    }

    public function testAccumulatesFlashesInOrder(): void
    {
        $response = new RedirectResponse('/some-url');
        $response->withFlash(Flash::Success, 'Item saved');
        $response->withFlash(Flash::Error, 'Something failed');

        self::assertSame([
            [
                'type' => Flash::Success,
                'message' => 'Item saved',
            ],
            [
                'type' => Flash::Error,
                'message' => 'Something failed',
            ],
        ], $response->getFlashes());
    }

    public function testWithFlashReturnsFluentSelf(): void
    {
        $response = new RedirectResponse('/some-url');
        $result = $response->withFlash(Flash::Info, 'Hello');

        self::assertSame($response, $result);
    }
}
