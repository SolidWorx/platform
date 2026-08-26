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

namespace SolidWorx\Platform\PlatformBundle\Logger\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use SolidWorx\Platform\PlatformBundle\Model\Tenant;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Uid\Ulid;

#[AutoconfigureTag('monolog.processor')]
final readonly class TenantLoggingProcessor implements ProcessorInterface
{
    public function __construct(
        private TenantContext $tenantContext,
        private TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $tenantId = $this->tenantContext->getTenantId();

        if ($tenantId instanceof Ulid) {
            $tenant = $this->tenantRepository->find($tenantId);

            if ($tenant instanceof Tenant) {
                $record->extra['tenant'] = [
                    'id' => $tenant->getId()->toHex(),
                    'name' => $tenant->getName(),
                ];
            }
        }

        return $record;
    }
}
