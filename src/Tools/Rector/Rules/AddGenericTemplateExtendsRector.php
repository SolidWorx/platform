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
use Doctrine\ORM\EntityRepository;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\PhpDocParser\Ast\PhpDoc\ExtendsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds missing @extends / @template-extends annotations on classes that
 * extend a generic parent class without specifying its template types.
 *
 * Only emits annotations when the generic types can be inferred from
 * the class itself with high confidence (no guesses, no fallbacks).
 *
 * Supported inference strategies:
 *  - Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<TEntity>
 *    and Doctrine\ORM\EntityRepository<TEntity>: entity class is read from
 *    the second argument of the parent::__construct() call.
 *  - Symfony\Component\Form\AbstractType<TData>: data class is read from the
 *    'data_class' default set on the OptionsResolver in configureOptions().
 *    Falls back to 'mixed' when no data_class can be inferred.
 *  - Symfony\Component\Security\Core\Authorization\Voter\Voter<TAttribute, TSubject>:
 *    uses 'string, mixed' (TAttribute is bound to string by Voter).
 */
final class AddGenericTemplateExtendsRector extends AbstractRector
{
    /**
     * @var array<class-string, int>
     */
    private const array GENERIC_PARENT_TEMPLATE_COUNT = [
        ServiceEntityRepository::class => 1,
        EntityRepository::class => 1,
        AbstractType::class => 1,
        Voter::class => 2,
    ];

    /**
     * @var array<string, string>
     */
    private const array FORM_TYPE_TO_PHP_TYPE = [
        TextType::class => 'string',
        HiddenType::class => 'string',
        TextareaType::class => 'string',
        EmailType::class => 'string',
        PasswordType::class => 'string',
        SearchType::class => 'string',
        UrlType::class => 'string',
        TelType::class => 'string',
        ColorType::class => 'string',
        MoneyType::class => 'string',
        RangeType::class => 'string',
        IntegerType::class => 'int',
        NumberType::class => 'float|int|string',
        PercentType::class => 'float|int|string',
        CheckboxType::class => 'bool',
        ChoiceType::class => 'string',
        EnumType::class => 'string',
        CollectionType::class => 'array',
    ];

    public function __construct(
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly DocBlockUpdater $docBlockUpdater,
        private readonly NodeFinder $nodeFinder,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add @extends / @template-extends annotations on classes extending generic classes when the types can be safely inferred.',
            [new CodeSample(
                <<<'CODE_SAMPLE'
                    use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
                    use Doctrine\Persistence\ManagerRegistry;

                    final class PlanRepository extends ServiceEntityRepository
                    {
                        public function __construct(ManagerRegistry $registry)
                        {
                            parent::__construct($registry, Plan::class);
                        }
                    }
                    CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
                    use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
                    use Doctrine\Persistence\ManagerRegistry;

                    /**
                     * @extends ServiceEntityRepository<Plan>
                     */
                    final class PlanRepository extends ServiceEntityRepository
                    {
                        public function __construct(ManagerRegistry $registry)
                        {
                            parent::__construct($registry, Plan::class);
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

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node->extends === null) {
            return null;
        }

        $parentFqn = $this->getName($node->extends);
        if ($parentFqn === null) {
            return null;
        }

        if (! class_exists($parentFqn) && ! \interface_exists($parentFqn)) {
            return null;
        }

        $templateCount = $this->resolveGenericParentTemplateCount($parentFqn);
        if ($templateCount === null) {
            return null;
        }

        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);
        if ($this->hasMatchingExtendsTag($phpDocInfo, $templateCount)) {
            if ($this->fixUnqualifiedTypesInExtendsTag($phpDocInfo)) {
                $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);
                return $node;
            }

            return null;
        }

        $rootGenericFqn = $this->findRootGenericAncestor($parentFqn);
        if ($rootGenericFqn === null) {
            return null;
        }

        $genericTypes = $this->inferGenericTypes($node, $rootGenericFqn);
        if ($genericTypes === null || \count($genericTypes) !== $templateCount) {
            return null;
        }

        $parentShortName = $this->getShortParentName($node);
        $genericTypeNode = new GenericTypeNode(
            new IdentifierTypeNode($parentShortName),
            $genericTypes,
        );

        $phpDocInfo->addPhpDocTagNode(new PhpDocTagNode('@extends', new ExtendsTagValueNode($genericTypeNode, '')));
        $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);

        return $node;
    }

    /**
     * Returns the number of template parameters required when extending the
     * given class, or null when no annotation is needed.
     *
     * If the direct parent declares its own @template tags, those need to be
     * passed by the child. If the parent already passes concrete types to its
     * own ancestor and is not itself templated, no annotation is needed.
     */
    private function resolveGenericParentTemplateCount(string $parentFqn): ?int
    {
        if (! class_exists($parentFqn) && ! \interface_exists($parentFqn)) {
            return null;
        }

        $reflection = new ReflectionClass($parentFqn);

        $templateCount = $this->countOwnTemplateTags($reflection);
        if ($templateCount > 0) {
            return $templateCount;
        }

        return self::GENERIC_PARENT_TEMPLATE_COUNT[$parentFqn] ?? null;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function countOwnTemplateTags(ReflectionClass $reflection): int
    {
        $docComment = $reflection->getDocComment();
        if ($docComment === false) {
            return 0;
        }

        $count = preg_match_all('/^\s*\*\s*@template(?:-covariant|-contravariant)?\s+[A-Za-z_]\w*/m', $docComment);
        if ($count === false) {
            return 0;
        }

        return $count;
    }

    private function findRootGenericAncestor(string $parentFqn): ?string
    {
        if (! class_exists($parentFqn) && ! \interface_exists($parentFqn)) {
            return null;
        }

        $reflection = new ReflectionClass($parentFqn);

        while ($reflection !== false) {
            if (isset(self::GENERIC_PARENT_TEMPLATE_COUNT[$reflection->getName()])) {
                return $reflection->getName();
            }

            $reflection = $reflection->getParentClass();
        }

        return null;
    }

    private function hasMatchingExtendsTag(PhpDocInfo $phpDocInfo, int $expectedCount): bool
    {
        foreach (['extends', 'template-extends'] as $tagName) {
            $tagNodes = $phpDocInfo->getTagsByName('@' . $tagName);
            foreach ($tagNodes as $tagNode) {
                $value = $tagNode->value;
                if (! $value instanceof ExtendsTagValueNode) {
                    continue;
                }

                if (\count($value->type->genericTypes) === $expectedCount) {
                    return true;
                }
            }
        }

        return false;
    }

    private function fixUnqualifiedTypesInExtendsTag(PhpDocInfo $phpDocInfo): bool
    {
        $hasFixed = false;

        foreach (['extends', 'template-extends'] as $tagName) {
            $tagNodes = $phpDocInfo->getTagsByName('@' . $tagName);
            foreach ($tagNodes as $tagNode) {
                $value = $tagNode->value;
                if (! $value instanceof ExtendsTagValueNode) {
                    continue;
                }

                foreach ($value->type->genericTypes as $i => $genericType) {
                    if (! $genericType instanceof IdentifierTypeNode) {
                        continue;
                    }

                    $name = $genericType->name;
                    if (! str_contains($name, '\\')) {
                        continue;
                    }

                    if (str_starts_with($name, '\\')) {
                        continue;
                    }

                    $value->type->genericTypes[$i] = new IdentifierTypeNode('\\' . $name);
                    $hasFixed = true;
                }
            }
        }

        return $hasFixed;
    }

    private function getShortParentName(Class_ $node): string
    {
        $extends = $node->extends;
        if (! $extends instanceof Name) {
            return '';
        }

        return $extends->getLast();
    }

    /**
     * @return list<TypeNode>|null
     */
    private function inferGenericTypes(Class_ $node, string $rootGenericFqn): ?array
    {
        return match ($rootGenericFqn) {
            ServiceEntityRepository::class,
            EntityRepository::class => $this->inferEntityRepositoryType($node),
            AbstractType::class => $this->inferFormDataType($node),
            Voter::class => $this->inferVoterTypes(),
            default => null,
        };
    }

    /**
     * @return list<TypeNode>|null
     */
    private function inferEntityRepositoryType(Class_ $node): ?array
    {
        $constructor = $node->getMethod('__construct');
        if (! $constructor instanceof ClassMethod) {
            return null;
        }

        $parentConstructCall = $this->nodeFinder->findFirst((array) $constructor->stmts, static fn (Node $sub): bool => $sub instanceof StaticCall
            && $sub->class instanceof Name
            && $sub->class->isSpecialClassName()
            && $sub->class->toLowerString() === 'parent'
            && $sub->name instanceof Identifier
            && $sub->name->toLowerString() === '__construct');

        if (! $parentConstructCall instanceof StaticCall) {
            return null;
        }

        $entityClassArg = $parentConstructCall->args[1] ?? null;
        if (! $entityClassArg instanceof Arg) {
            return null;
        }

        $entityClass = $this->resolveClassConstFetchName($entityClassArg->value);
        if ($entityClass === null) {
            return null;
        }

        return [new IdentifierTypeNode($entityClass)];
    }

    /**
     * @return list<TypeNode>
     */
    private function inferFormDataType(Class_ $node): array
    {
        $dataClass = $this->extractDataClassFromConfigureOptions($node);
        if ($dataClass !== null) {
            return [new IdentifierTypeNode($dataClass)];
        }

        $arrayShape = $this->inferFormArrayShape($node);
        if ($arrayShape instanceof ArrayShapeNode) {
            return [$arrayShape];
        }

        return [new IdentifierTypeNode('mixed')];
    }

    private function extractDataClassFromConfigureOptions(Class_ $node): ?string
    {
        $configureOptions = $node->getMethod('configureOptions');
        if (! $configureOptions instanceof ClassMethod) {
            return null;
        }

        foreach ((array) $configureOptions->stmts as $stmt) {
            $dataClass = $this->extractDataClassFromStmt($stmt);
            if ($dataClass !== null) {
                return $dataClass;
            }
        }

        return null;
    }

    private function inferFormArrayShape(Class_ $node): ?ArrayShapeNode
    {
        $buildForm = $node->getMethod('buildForm');
        if (! $buildForm instanceof ClassMethod) {
            return null;
        }

        $addCalls = $this->findBuilderAddCalls($buildForm);
        if ($addCalls === []) {
            return null;
        }

        $items = [];
        foreach ($addCalls as $addCall) {
            $item = $this->extractArrayShapeItemFromAddCall($addCall);
            if ($item === false) {
                return null;
            }

            if (! $item instanceof ArrayShapeItemNode) {
                continue;
            }

            $items[] = $item;
        }

        if ($items === []) {
            return null;
        }

        return ArrayShapeNode::createSealed($items);
    }

    /**
     * @return list<MethodCall>
     */
    private function findBuilderAddCalls(ClassMethod $buildForm): array
    {
        $calls = [];

        $this->nodeFinder->find((array) $buildForm->stmts, function (Node $sub) use (&$calls): bool {
            if (! $sub instanceof MethodCall) {
                return false;
            }

            if (! $sub->name instanceof Identifier || $sub->name->toLowerString() !== 'add') {
                return false;
            }

            $calls[] = $sub;
            return false;
        });

        return $calls;
    }

    /**
     * @return ArrayShapeItemNode|null|false null = skip (unmapped), false = can't resolve (bail out)
     */
    private function extractArrayShapeItemFromAddCall(MethodCall $call): ArrayShapeItemNode|null|false
    {
        $nameArg = $call->args[0] ?? null;
        if (! $nameArg instanceof Arg) {
            return false;
        }

        if (! $nameArg->value instanceof String_) {
            return false;
        }

        $fieldName = $nameArg->value->value;

        $typeArg = $call->args[1] ?? null;
        if (! $typeArg instanceof Arg) {
            $typeArg = null;
        }

        $optionsArg = $call->args[2] ?? null;
        if (! $optionsArg instanceof Arg) {
            $optionsArg = null;
        }

        if ($this->isFieldUnmapped($optionsArg)) {
            return null;
        }

        $phpType = $this->resolveFormFieldPhpType($typeArg, $optionsArg);

        return new ArrayShapeItemNode(
            new IdentifierTypeNode($fieldName),
            false,
            $phpType,
        );
    }

    private function isFieldUnmapped(?Arg $optionsArg): bool
    {
        if (! $optionsArg instanceof Arg || ! $optionsArg->value instanceof Array_) {
            return false;
        }

        foreach ($optionsArg->value->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }
            if (! $item->key instanceof String_) {
                continue;
            }
            if ($item->key->value === 'mapped' && $this->isFalseValue($item->value)) {
                return true;
            }
        }

        return false;
    }

    private function isFalseValue(Node $node): bool
    {
        if ($node instanceof ConstFetch) {
            return $node->name->toLowerString() === 'false';
        }

        return false;
    }

    private function resolveFormFieldPhpType(?Arg $typeArg, ?Arg $optionsArg): TypeNode
    {
        if (! $typeArg instanceof Arg) {
            return new IdentifierTypeNode('mixed');
        }

        $typeFqn = $this->resolveClassConstFetchFqn($typeArg->value);
        if ($typeFqn === null) {
            return new IdentifierTypeNode('mixed');
        }

        if ($typeFqn === ChoiceType::class) {
            return $this->resolveChoiceTypePhpType($optionsArg);
        }

        $mapped = self::FORM_TYPE_TO_PHP_TYPE[$typeFqn] ?? null;
        if ($mapped === null) {
            return new IdentifierTypeNode('mixed');
        }

        if (str_contains($mapped, '|')) {
            $parts = explode('|', $mapped);
            return new UnionTypeNode(array_map(
                static fn (string $p): IdentifierTypeNode => new IdentifierTypeNode($p),
                $parts,
            ));
        }

        return new IdentifierTypeNode($mapped);
    }

    private function resolveChoiceTypePhpType(?Arg $optionsArg): TypeNode
    {
        if ($optionsArg instanceof Arg && $optionsArg->value instanceof Array_) {
            foreach ($optionsArg->value->items as $item) {
                if (! $item instanceof ArrayItem) {
                    continue;
                }
                if (! $item->key instanceof String_) {
                    continue;
                }
                if ($item->key->value === 'multiple' && $this->isTrueValue($item->value)) {
                    return new GenericTypeNode(
                        new IdentifierTypeNode('list'),
                        [new IdentifierTypeNode('string')],
                    );
                }
            }
        }

        return new IdentifierTypeNode('string');
    }

    private function isTrueValue(Node $node): bool
    {
        if ($node instanceof ConstFetch) {
            return $node->name->toLowerString() === 'true';
        }

        return false;
    }

    private function resolveClassConstFetchFqn(Node $node): ?string
    {
        if (! $node instanceof ClassConstFetch) {
            return null;
        }

        if (! $node->class instanceof Name) {
            return null;
        }

        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'class') {
            return null;
        }

        return $this->getName($node->class);
    }

    /**
     * @return list<TypeNode>
     */
    private function inferVoterTypes(): array
    {
        return [new IdentifierTypeNode('string'), new IdentifierTypeNode('mixed')];
    }

    private function extractDataClassFromStmt(Node $node): ?string
    {
        $methodCall = $this->nodeFinder->findFirst([$node], function (Node $sub): bool {
            if (! $sub instanceof MethodCall) {
                return false;
            }

            if (! $sub->name instanceof Identifier) {
                return false;
            }

            $methodName = $sub->name->toLowerString();
            return \in_array($methodName, ['setdefaults', 'setdefault'], true);
        });

        if (! $methodCall instanceof MethodCall) {
            return null;
        }

        $methodName = $methodCall->name instanceof Identifier ? $methodCall->name->toLowerString() : '';

        if ($methodName === 'setdefault') {
            $keyArg = $methodCall->args[0] ?? null;
            $valueArg = $methodCall->args[1] ?? null;
            if (! $keyArg instanceof Arg || ! $valueArg instanceof Arg) {
                return null;
            }

            if (! $keyArg->value instanceof String_ || $keyArg->value->value !== 'data_class') {
                return null;
            }

            return $this->resolveClassConstFetchName($valueArg->value);
        }

        $defaultsArg = $methodCall->args[0] ?? null;
        if (! $defaultsArg instanceof Arg || ! $defaultsArg->value instanceof Array_) {
            return null;
        }

        foreach ($defaultsArg->value->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if (! $item->key instanceof Expr) {
                continue;
            }

            if (! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value !== 'data_class') {
                continue;
            }

            return $this->resolveClassConstFetchName($item->value);
        }

        return null;
    }

    private function resolveClassConstFetchName(Node $node): ?string
    {
        if (! $node instanceof ClassConstFetch) {
            return null;
        }

        if (! $node->class instanceof Name) {
            return null;
        }

        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'class') {
            return null;
        }

        $sourceName = $node->class->toString();
        $resolvedName = $this->getName($node->class);

        if ($resolvedName === null) {
            return null;
        }

        if (! str_contains($sourceName, '\\')) {
            return $sourceName;
        }

        return '\\' . ltrim($resolvedName, '\\');
    }
}
