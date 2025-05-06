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

namespace SolidWorx\Platform\SaasBundle\Doctrine\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use function array_key_exists;

final readonly class MetadataSubscriber
{
    /**
     * @param array<class-string, string> $dbNames
     */
    public function __construct(
        #[Autowire(param: 'saas.doctrine.db_schema.table_names')]
        private array $dbNames
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $classMetadata = $event->getClassMetadata();
        if (array_key_exists($classMetadata->getName(), $this->dbNames)) {
            $classMetadata->setPrimaryTable([
                'name' => $this->dbNames[$classMetadata->getName()],
            ]);
        }
    }
}
