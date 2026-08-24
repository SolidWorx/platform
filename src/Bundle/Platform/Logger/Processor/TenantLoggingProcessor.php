<?php

namespace SolidWorx\Platform\PlatformBundle\Logger\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use SolidWorx\Platform\PlatformBundle\Model\Tenant;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('monolog.processor')]
final class TenantLoggingProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $tenantId = $this->tenantContext->getTenantId();


        if ($tenantId !== null) {
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
