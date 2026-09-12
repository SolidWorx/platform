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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Tenant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantSessionStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantSessionStorage::class)]
final class TenantSessionStorageTest extends TestCase
{
    private const string SESSION_KEY = '_tenant_id';

    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testRoundTripsTheTenantId(): void
    {
        $storage = $this->createStorage();
        $tenantId = new Ulid();

        $storage->setTenantId($tenantId);

        $this->assertTrue($tenantId->equals($storage->getTenantId() ?? new Ulid()));
        $this->assertSame($tenantId->toRfc4122(), $this->session->get(self::SESSION_KEY));
    }

    public function testClearsTheTenantId(): void
    {
        $storage = $this->createStorage();
        $storage->setTenantId(new Ulid());

        $storage->clearTenantId();

        $this->assertNull($storage->getTenantId());
    }

    public function testReturnsNullForAMalformedTenantId(): void
    {
        $storage = $this->createStorage();
        $this->session->set(self::SESSION_KEY, 'not-a-ulid');

        $this->assertNull($storage->getTenantId());
    }

    public function testDegradesToNoOpWithoutARequest(): void
    {
        $storage = new TenantSessionStorage(new RequestStack(), self::SESSION_KEY);

        $storage->setTenantId(new Ulid());

        $this->assertNull($storage->getTenantId());
        $this->assertNull($storage->consumeTargetPath());
    }

    public function testConsumingTheTargetPathForgetsIt(): void
    {
        $storage = $this->createStorage();
        $storage->setTargetPath('/invoices/123?page=2');

        $this->assertSame('/invoices/123?page=2', $storage->consumeTargetPath());
        $this->assertNull($storage->consumeTargetPath());
    }

    /**
     * A stored target is replayed as a redirect, so anything that could point off-site has to be
     * refused outright rather than sanitised later.
     */
    #[DataProvider('offSiteTargets')]
    public function testRefusesTargetsThatAreNotLocalPaths(string $target): void
    {
        $storage = $this->createStorage();

        $storage->setTargetPath($target);

        $this->assertNull($storage->consumeTargetPath());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function offSiteTargets(): iterable
    {
        yield 'absolute url' => ['https://evil.example.com/'];
        yield 'protocol relative' => ['//evil.example.com/'];
        yield 'scheme relative' => ['javascript:alert(1)'];
        yield 'bare path' => ['invoices'];
    }

    private function createStorage(): TenantSessionStorage
    {
        $request = new Request();
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new TenantSessionStorage($requestStack, self::SESSION_KEY);
    }
}
