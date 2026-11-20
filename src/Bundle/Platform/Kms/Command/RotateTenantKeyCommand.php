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

namespace SolidWorx\Platform\PlatformBundle\Kms\Command;

use Override;
use SolidWorx\Platform\PlatformBundle\Console\Command;
use SolidWorx\Platform\PlatformBundle\Kms\TenantEnvelopeEncrypter;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\Uid\Ulid;
use function count;
use function is_string;
use function sprintf;

#[AsCommand(
    name: 'platform:kms:rotate-tenant',
    description: 'Creates a new data key for one tenant, or for all tenants; values already written keep decrypting.',
)]
final class RotateTenantKeyCommand extends Command
{
    public function __construct(
        // The real store, not the DataKeyStoreInterface alias, which is the traceable wrapper in debug.
        #[Autowire(service: 'key_management.store')]
        private readonly RewrappableDataKeyStoreInterface $store,
        private readonly TenantRepository $tenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('tenant', InputArgument::OPTIONAL, 'ULID of the tenant whose data key to rotate');
        $this->addOption('all', mode: InputOption::VALUE_NONE, description: 'Rotate the data key of every tenant');
    }

    #[Override]
    protected function handle(): int
    {
        $tenant = $this->io->getArgument('tenant');
        $all = $this->io->getOption('all') === true;

        if (($tenant === null) === ! $all) {
            $this->io->error('Pass either a tenant ULID or --all, not both and not neither.');

            return self::FAILURE;
        }

        if ($all) {
            // ponytail: hydrates every tenant; select ids only if tenant counts get large.
            $ids = [];
            foreach ($this->tenants->findAll() as $entity) {
                $ids[] = $entity->getId();
            }
        } else {
            if (! is_string($tenant) || ! Ulid::isValid($tenant, Ulid::FORMAT_BASE_32)) {
                $this->io->error('The tenant must be a valid ULID.');

                return self::FAILURE;
            }

            $id = Ulid::fromString($tenant);
            if ($this->tenants->find($id) === null) {
                $this->io->error(sprintf('No tenant %s exists.', $id));

                return self::FAILURE;
            }

            $ids = [$id];
        }

        foreach ($ids as $id) {
            $this->store->rotate(TenantEnvelopeEncrypter::SCOPE_PREFIX . $id->toBase32());
            $this->io->writeln((string) $id);
        }

        $this->io->success(sprintf('Rotated the data key of %d tenant(s).', count($ids)));

        return self::SUCCESS;
    }
}
