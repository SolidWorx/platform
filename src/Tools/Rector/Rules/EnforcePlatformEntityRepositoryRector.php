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

namespace SolidWorx\Platform\Tools\Rector\Rules;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository as DoctrineEntityRepository;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\PhpDocParser\Ast\PhpDoc\ExtendsTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository as PlatformEntityRepository;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Ensures all Doctrine repository classes extend the platform's EntityRepository
 * instead of Doctrine's ServiceEntityRepository or EntityRepository directly.
 *
 * Accepts intermediate base repositories as long as the platform's EntityRepository
 * is somewhere in the parent chain.
 */
final class EnforcePlatformEntityRepositoryRector extends AbstractRector
{
    private const array DOCTRINE_REPOSITORY_CLASSES = [
        ServiceEntityRepository::class,
        DoctrineEntityRepository::class,
    ];

    private const array DOCTRINE_SHORT_NAMES = [
        'ServiceEntityRepository',
        'EntityRepository',
    ];

    public function __construct(
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly DocBlockUpdater $docBlockUpdater,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace direct Doctrine repository inheritance with the platform EntityRepository.',
            [new CodeSample(
                <<<'CODE_SAMPLE'
                    use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
                    use Doctrine\Persistence\ManagerRegistry;

                    final class InvoiceRepository extends ServiceEntityRepository
                    {
                        public function __construct(ManagerRegistry $registry)
                        {
                            parent::__construct($registry, Invoice::class);
                        }
                    }
                    CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
                    use Doctrine\Persistence\ManagerRegistry;
                    use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;

                    final class InvoiceRepository extends EntityRepository
                    {
                        public function __construct(ManagerRegistry $registry)
                        {
                            parent::__construct($registry, Invoice::class);
                        }
                    }
                    CODE_SAMPLE
                ,
            )],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        if ($node->extends === null) {
            return null;
        }

        if ($node->isAbstract()) {
            return null;
        }

        $parentFqn = $this->getName($node->extends);
        if ($parentFqn === null) {
            return null;
        }

        if (! $this->isDoctrineRepository($parentFqn)) {
            return null;
        }

        if ($this->hasPlatformRepositoryInChain($parentFqn)) {
            return null;
        }

        if ($this->isDirectDoctrineParent($parentFqn)) {
            $node->extends = new Name('\\' . PlatformEntityRepository::class);
            $this->fixStaleExtendsAnnotation($node);
            return $node;
        }

        return null;
    }

    private function fixStaleExtendsAnnotation(Class_ $node): void
    {
        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);

        foreach (['extends', 'template-extends'] as $tagName) {
            foreach ($phpDocInfo->getTagsByName('@' . $tagName) as $tagNode) {
                $value = $tagNode->value;
                if (! $value instanceof ExtendsTagValueNode) {
                    continue;
                }

                $typeName = $value->type->type->name;
                if (! \in_array($typeName, self::DOCTRINE_SHORT_NAMES, true)) {
                    continue;
                }

                $value->type = new GenericTypeNode(
                    new IdentifierTypeNode('EntityRepository'),
                    $value->type->genericTypes,
                );
                $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);
                return;
            }
        }
    }

    private function isDoctrineRepository(string $classFqn): bool
    {
        if (! class_exists($classFqn)) {
            return false;
        }

        foreach (self::DOCTRINE_REPOSITORY_CLASSES as $doctrineClass) {
            if ($classFqn === $doctrineClass || is_subclass_of($classFqn, $doctrineClass)) {
                return true;
            }
        }

        return false;
    }

    private function hasPlatformRepositoryInChain(string $classFqn): bool
    {
        if ($classFqn === PlatformEntityRepository::class) {
            return true;
        }

        if (! class_exists($classFqn) && ! \interface_exists($classFqn)) {
            return false;
        }

        $reflection = new ReflectionClass($classFqn);

        while (($parent = $reflection->getParentClass()) !== false) {
            $reflection = $parent;
            if ($reflection->getName() === PlatformEntityRepository::class) {
                return true;
            }
        }

        return false;
    }

    private function isDirectDoctrineParent(string $parentFqn): bool
    {
        return \in_array($parentFqn, self::DOCTRINE_REPOSITORY_CLASSES, true);
    }
}
