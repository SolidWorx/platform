<?php

namespace SolidWorx\Platform\PlatformBundle\DataCollector;

use SolidWorx\Platform\PlatformBundle\Model\Tenant;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\VarDumper\Cloner\Data;
use function method_exists;

#[AutoconfigureTag(
    name: 'data_collector',
    attributes: [
        'id' => self::COLLECTOR_ID,
        'priority' => -255,
    ],
)]
final class TenantDataCollector extends AbstractDataCollector
{
    private const string COLLECTOR_ID = 'tenant';
    private const string COLLECTOR_TEMPLATE = '@Platform/DataCollector/tenant.html.twig';

    public function __construct(
        private readonly TenantContext    $tenantContext,
        private readonly TenantRepository $tenantRepository,
        private readonly UserTenantRepository $userTenantRepository,
        private readonly Security $security,
    ) {}

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $tenantId = $this->tenantContext->getTenantId();
        $user = $this->security->getUser();

        $userTenants = [];

        if ($user instanceof User && method_exists($user, 'getId')) {
            $userTenants = $this->userTenantRepository->findTenantsForUser($user->getId());
        }

        $this->data = [
            'tenant' => $tenantId !== null ? $this->tenantRepository->find($tenantId) : null,
            'user_tenants' => $userTenants,
        ];
    }

    public function getName(): string
    {
        return self::COLLECTOR_ID;
    }

    public function getTenant(): ?Tenant
    {
        // @phpstan-ignore return.type
        return $this->data['tenant'] ?? null;
    }

    public function getTenantData(): ?Data
    {
        return isset($this->data['tenant']) ? $this->cloneVar($this->data['tenant']) : null;
    }

    /**
     * @return list<array{id: Ulid, name: string}>
     */
    public function getUserTenants(): array
    {
        // @phpstan-ignore return.type
        return $this->data['user_tenants'] ?? [];
    }

    public static function getTemplate(): string
    {
        return self::COLLECTOR_TEMPLATE;
    }
}
