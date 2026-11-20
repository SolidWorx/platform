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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Kms\Command;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionProperty;
use SolidWorx\Platform\PlatformBundle\Console\IO;
use SolidWorx\Platform\PlatformBundle\Kms\Command\RotateTenantKeyCommand;
use SolidWorx\Platform\PlatformBundle\Kms\LocalKeyManager;
use SolidWorx\Platform\PlatformBundle\Kms\TenantEnvelopeEncrypter;
use SolidWorx\Platform\PlatformBundle\Kms\TenantScopeRegistry;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\Entity\EncryptedTenantNote;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\KmsOrmTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\Uid\Ulid;
use function strlen;
use function substr;

#[CoversClass(RotateTenantKeyCommand::class)]
#[UsesClass(IO::class)]
#[UsesClass(TenantEnvelopeEncrypter::class)]
#[UsesClass(LocalKeyManager::class)]
#[RequiresPhpExtension('sodium')]
#[RequiresPhpExtension('openssl')]
final class RotateTenantKeyCommandTest extends KmsOrmTestCase
{
    private BufferedOutput $output;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->output = new BufferedOutput();
    }

    public function testRotatesOneTenant(): void
    {
        $tenant = new Ulid();
        $other = new Ulid();
        $store = $this->seededStore($tenant, $other);

        self::assertSame(Command::SUCCESS, $this->execute($store, [$this->tenant($tenant), $this->tenant($other)], [
            'tenant' => (string) $tenant,
        ]));
        self::assertSame([
            $tenant->toBase32() => 2,
            $other->toBase32() => 1,
        ], $this->keysPerTenant($store));
        self::assertStringContainsString((string) $tenant, $this->output->fetch());
    }

    public function testRotatesAllTenants(): void
    {
        $a = new Ulid();
        $b = new Ulid();
        $store = $this->seededStore($a, $b);

        self::assertSame(Command::SUCCESS, $this->execute($store, [$this->tenant($a), $this->tenant($b)], [
            '--all' => true,
        ]));
        self::assertSame([
            $a->toBase32() => 2,
            $b->toBase32() => 2,
        ], $this->keysPerTenant($store));
        self::assertStringContainsString('2 tenant', $this->output->fetch());
    }

    public function testRequiresTenantOrAll(): void
    {
        $tenant = new Ulid();
        $store = $this->seededStore($tenant);

        self::assertSame(Command::FAILURE, $this->execute($store, [$this->tenant($tenant)], []));
        self::assertSame([
            $tenant->toBase32() => 1,
        ], $this->keysPerTenant($store));
    }

    public function testRejectsBoth(): void
    {
        $tenant = new Ulid();
        $store = $this->seededStore($tenant);

        self::assertSame(Command::FAILURE, $this->execute($store, [$this->tenant($tenant)], [
            'tenant' => (string) $tenant,
            '--all' => true,
        ]));
        self::assertSame([
            $tenant->toBase32() => 1,
        ], $this->keysPerTenant($store));
    }

    public function testInvalidUlidFails(): void
    {
        $store = new InMemoryDataKeyStore();

        self::assertSame(Command::FAILURE, $this->execute($store, [], [
            'tenant' => 'not-a-ulid',
        ]));
        self::assertSame([], $this->keysPerTenant($store));
    }

    public function testUnknownTenantFails(): void
    {
        $store = new InMemoryDataKeyStore();

        self::assertSame(Command::FAILURE, $this->execute($store, [], [
            'tenant' => (string) new Ulid(),
        ]));
        self::assertSame([], $this->keysPerTenant($store));
    }

    public function testOldValuesStillDecryptAndNewWritesUseNewKey(): void
    {
        $store = new InMemoryDataKeyStore();
        $encrypter = new StoredEnvelopeEncrypter($store);
        $tenant = new Ulid();
        $scope = TenantEnvelopeEncrypter::SCOPE_PREFIX . $tenant->toBase32();

        $before = $encrypter->encrypt($scope, 'old secret');

        self::assertSame(Command::SUCCESS, $this->execute($store, [$this->tenant($tenant)], [
            'tenant' => (string) $tenant,
        ]));

        $after = $encrypter->encrypt($scope, 'new secret');
        self::assertNotSame($before->reference, $after->reference);
        self::assertSame('old secret', $encrypter->decrypt($before));
        self::assertSame('new secret', $encrypter->decrypt($after));
    }

    public function testRotatesTheScopeTheTenantEncrypterUses(): void
    {
        $context = new TenantContext(new EventDispatcher());
        $em = $this->createKmsEntityManager(
            __DIR__ . '/../../Fixtures/Kms/Entity',
            decorate: fn (StoredEnvelopeEncrypter $inner): TenantEnvelopeEncrypter => new TenantEnvelopeEncrypter(
                $inner,
                new TenantScopeRegistry($this->managerRegistry()),
                $context,
            ),
        );
        $tenant = new Ulid();
        $context->setTenant($tenant);
        $em->persist($note = new EncryptedTenantNote('secret'));
        $note->setTenantId($tenant);
        $em->flush();

        $store = new ReflectionProperty(StoredEnvelopeEncrypter::class, 'store')->getValue($this->encrypter);
        self::assertInstanceOf(InMemoryDataKeyStore::class, $store);
        self::assertSame([
            $tenant->toBase32() => 1,
        ], $this->keysPerTenant($store));

        self::assertSame(Command::SUCCESS, $this->execute($store, [$this->tenant($tenant)], [
            'tenant' => (string) $tenant,
        ]));
        self::assertSame([
            $tenant->toBase32() => 2,
        ], $this->keysPerTenant($store));
    }

    public function testRefusesALocalKeyOutsideDevAndTest(): void
    {
        $tenant = new Ulid();
        $store = $this->seededStore($tenant);

        self::assertSame(Command::FAILURE, $this->execute($store, [$this->tenant($tenant)], [
            'tenant' => (string) $tenant,
        ], new LocalKeyManager('sodium+dir:///nonexistent?ext=.key', 'platform', 'prod', false, null, 'secret')));
        self::assertSame([
            $tenant->toBase32() => 1,
        ], $this->keysPerTenant($store));
        self::assertStringContainsString('Refusing a local master key', $this->output->fetch());
    }

    private function seededStore(Ulid ...$tenants): InMemoryDataKeyStore
    {
        $store = new InMemoryDataKeyStore();
        foreach ($tenants as $tenant) {
            $store->current(TenantEnvelopeEncrypter::SCOPE_PREFIX . $tenant->toBase32());
        }

        return $store;
    }

    /**
     * @return array<string, int> number of stored data keys per tenant, indexed by base32 ULID
     */
    private function keysPerTenant(InMemoryDataKeyStore $store): array
    {
        $counts = [];
        foreach ($store->all() as $row) {
            $key = substr($row->scope, strlen(TenantEnvelopeEncrypter::SCOPE_PREFIX));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<TenantInterface> $tenants
     * @param array<string, mixed> $parameters
     */
    private function execute(RewrappableDataKeyStoreInterface $store, array $tenants, array $parameters, ?LocalKeyManager $localKeys = null): int
    {
        // Allowed: the guard is exercised on its own above.
        $localKeys ??= new LocalKeyManager('sodium+dir:///nonexistent?ext=.key', 'platform', 'prod', true, null, 'secret');

        $repository = self::createStub(TenantRepository::class);
        $repository->method('findAll')->willReturn($tenants);
        $repository->method('find')->willReturnCallback(
            static function (mixed $id) use ($tenants): ?TenantInterface {
                foreach ($tenants as $tenant) {
                    if ($tenant->getId()->equals($id)) {
                        return $tenant;
                    }
                }

                return null;
            },
        );

        $command = new RotateTenantKeyCommand($store, $repository, $localKeys);
        $input = new ArrayInput($parameters, $command->getDefinition());
        $command->setIo(new IO($input, $this->output));

        return $command->run($input, $this->output);
    }

    private function tenant(Ulid $id): TenantInterface
    {
        $tenant = self::createStub(TenantInterface::class);
        $tenant->method('getId')->willReturn($id);

        return $tenant;
    }
}
