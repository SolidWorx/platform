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

namespace SolidWorx\Platform\PlatformBundle\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use InvalidArgumentException;
use Override;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareInterface;
use function sprintf;

/**
 * Restricts every query against a {@see TenantAwareInterface} entity to the tenant currently in
 * scope.
 *
 * The filter is parameter-driven: it only contributes a constraint while the `tenant_id` parameter
 * is bound (the {@see \SolidWorx\Platform\PlatformBundle\Tenant\TenantFilterSynchronizer} enables
 * the filter and binds the parameter on switch, and disables it when no tenant is in scope).
 *
 * Because the parameter is bound with the {@see \Symfony\Bridge\Doctrine\Types\UlidType}, Doctrine
 * converts and quotes it through the active platform connection, so the emitted SQL literal matches
 * exactly how the `tenant_id` column is stored on each database platform.
 */
final class TenantFilter extends SQLFilter
{
    public const string NAME = 'tenant';

    public const string PARAMETER = 'tenant_id';

    /**
     * @param string $targetTableAlias
     */
    #[Override]
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if ($targetEntity->reflClass?->implementsInterface(TenantAwareInterface::class) !== true) {
            return '';
        }

        if (! $targetEntity->hasField('tenantId')) {
            return '';
        }

        try {
            $tenantId = $this->getParameter(self::PARAMETER);
        } catch (InvalidArgumentException) {
            // Parameter not set -> no tenant in scope, contribute nothing.
            return '';
        }

        return sprintf('%s.%s = %s', $targetTableAlias, $targetEntity->getColumnName('tenantId'), $tenantId);
    }
}
