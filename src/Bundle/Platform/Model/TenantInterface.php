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

namespace SolidWorx\Platform\PlatformBundle\Model;

use DateTimeImmutable;
use Stringable;
use Symfony\Component\Uid\Ulid;

/**
 * Contract for the tenant boundary entity.
 *
 * Extend {@see Tenant} (the mapped base) to add your own fields and register the concrete class via
 * `platform.multi_tenancy.models.tenant`. Associations target this interface and are wired with
 * Doctrine `resolve_target_entities`.
 */
interface TenantInterface extends Stringable
{
    public function getId(): Ulid;

    public function getName(): string;

    public function setName(string $name): static;

    public function getDomain(): ?string;

    public function setDomain(?string $domain): static;

    public function getCreatedById(): ?Ulid;

    public function setCreatedById(?Ulid $createdById): static;

    public function getCreatedAt(): DateTimeImmutable;
}
