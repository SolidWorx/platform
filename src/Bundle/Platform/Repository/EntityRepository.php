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

namespace SolidWorx\Platform\PlatformBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use SolidWorx\Platform\PlatformBundle\Exception\InvalidEntityException;

/**
 * @template T of object
 * @template-extends ServiceEntityRepository<T>
 */
abstract class EntityRepository extends ServiceEntityRepository
{
    /**
     * @param T $entity
     */
    public function save(object $entity, bool $flush = true): void
    {
        if (! is_a($entity, $expected = $this->getEntityName())) {
            throw new InvalidEntityException($expected, $entity::class);
        }

        $em = $this->getEntityManager();
        $em->persist($entity);
        if ($flush) {
            $em->flush();
        }
    }
    
    /**
     * @param T $entity
     */
    public function remove(object $entity, bool $flush = true): void
    {
        if (!is_a($entity, $expected = $this->getEntityName())) {
            throw new InvalidEntityException($expected, $entity::class);
        }

        $em = $this->getEntityManager();
        $em->remove($entity);
        if ($flush) {
            $em->flush();
        }
    }
}
