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

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\UnionType;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds missing generic type parameters to method return types and parameters
 * when they reference known generic classes/interfaces without specifying types.
 *
 * Handles: FormInterface, FormTypeInterface, FormTypeExtensionInterface,
 * Doctrine\ORM\Query, Doctrine\ORM\Mapping\ClassMetadata.
 */
final class AddGenericMethodPhpDocRector extends AbstractRector
{
    /**
     * @var array<string, list<string>>
     */
    private const array GENERIC_TYPE_DEFAULTS = [
        FormInterface::class => ['mixed'],
        FormTypeInterface::class => ['mixed'],
        FormTypeExtensionInterface::class => ['mixed'],
        Query::class => ['mixed', 'mixed'],
        ClassMetadata::class => ['object'],
    ];

    public function __construct(
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly DocBlockUpdater $docBlockUpdater,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add generic type parameters to method return types and parameters that reference known generic classes without specifying types.',
            [new CodeSample(
                <<<'CODE_SAMPLE'
                    use Symfony\Component\Form\FormInterface;

                    class SomeComponent
                    {
                        public function instantiateForm(): FormInterface
                        {
                        }
                    }
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    use Symfony\Component\Form\FormInterface;

                    class SomeComponent
                    {
                        /**
                         * @return FormInterface<mixed>
                         */
                        public function instantiateForm(): FormInterface
                        {
                        }
                    }
                    CODE_SAMPLE,
            )],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        $hasChanged = false;

        if ($this->processReturnType($node)) {
            $hasChanged = true;
        }

        if ($this->processParameters($node)) {
            $hasChanged = true;
        }

        return $hasChanged ? $node : null;
    }

    private function processReturnType(ClassMethod $node): bool
    {
        $returnType = $node->returnType;
        if (!$returnType instanceof Node) {
            return false;
        }

        $genericFqn = $this->resolveGenericTypeFromTypeNode($returnType);
        if ($genericFqn === null) {
            return false;
        }

        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);
        if ($this->hasGenericReturnTag($phpDocInfo, $node)) {
            return false;
        }

        $defaults = self::GENERIC_TYPE_DEFAULTS[$genericFqn];
        $shortName = $this->getShortNameFromTypeNode($returnType);
        $isNullable = $returnType instanceof NullableType;

        $genericTypeNode = new GenericTypeNode(
            new IdentifierTypeNode($shortName),
            array_map(static fn (string $t): IdentifierTypeNode => new IdentifierTypeNode($t), $defaults),
        );

        $returnTypeNode = $isNullable
            ? new NullableTypeNode($genericTypeNode)
            : $genericTypeNode;

        $phpDocInfo->addPhpDocTagNode(new PhpDocTagNode('@return', new ReturnTagValueNode($returnTypeNode, '')));
        $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);

        return true;
    }

    private function processParameters(ClassMethod $node): bool
    {
        $hasChanged = false;

        foreach ($node->params as $param) {
            if ($this->processParam($node, $param)) {
                $hasChanged = true;
            }
        }

        return $hasChanged;
    }

    private function processParam(ClassMethod $node, Param $param): bool
    {
        if (!$param->type instanceof Node) {
            return false;
        }

        $genericFqn = $this->resolveGenericTypeFromTypeNode($param->type);
        if ($genericFqn === null) {
            return false;
        }

        $paramName = $param->var instanceof Variable && \is_string($param->var->name)
            ? '$' . $param->var->name
            : null;

        if ($paramName === null) {
            return false;
        }

        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);
        if ($this->hasGenericParamTag($phpDocInfo, $node, $paramName)) {
            return false;
        }

        $defaults = self::GENERIC_TYPE_DEFAULTS[$genericFqn];
        $shortName = $this->getShortNameFromTypeNode($param->type);
        $isNullable = $param->type instanceof NullableType;

        $genericTypeNode = new GenericTypeNode(
            new IdentifierTypeNode($shortName),
            array_map(static fn (string $t): IdentifierTypeNode => new IdentifierTypeNode($t), $defaults),
        );

        $typeNode = $isNullable
            ? new NullableTypeNode($genericTypeNode)
            : $genericTypeNode;

        $phpDocInfo->addPhpDocTagNode(new PhpDocTagNode('@param', new ParamTagValueNode($typeNode, false, $paramName, '', false)));
        $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);

        return true;
    }

    private function resolveGenericTypeFromTypeNode(Node $typeNode): ?string
    {
        if ($typeNode instanceof NullableType) {
            $typeNode = $typeNode->type;
        }

        if ($typeNode instanceof UnionType) {
            foreach ($typeNode->types as $type) {
                $result = $this->resolveGenericTypeFromTypeNode($type);
                if ($result !== null) {
                    return $result;
                }
            }

            return null;
        }

        if (! $typeNode instanceof Name && ! $typeNode instanceof Identifier) {
            return null;
        }

        $resolvedName = $typeNode instanceof Name ? $this->getName($typeNode) : $typeNode->toString();
        if ($resolvedName === null) {
            return null;
        }

        return isset(self::GENERIC_TYPE_DEFAULTS[$resolvedName]) ? $resolvedName : null;
    }

    private function getShortNameFromTypeNode(Node $typeNode): string
    {
        if ($typeNode instanceof NullableType) {
            $typeNode = $typeNode->type;
        }

        if ($typeNode instanceof Name) {
            return $typeNode->getLast();
        }

        if ($typeNode instanceof Identifier) {
            return $typeNode->toString();
        }

        return '';
    }

    private function hasGenericReturnTag(PhpDocInfo $phpDocInfo, ClassMethod $node): bool
    {
        $returnTags = $phpDocInfo->getTagsByName('@return');
        foreach ($returnTags as $tag) {
            if ($tag->value instanceof ReturnTagValueNode && $this->typeNodeHasGenericTypes($tag->value->type)) {
                return true;
            }
        }

        return $this->rawCommentsContainGenericReturn($node);
    }

    private function rawCommentsContainGenericReturn(ClassMethod $node): bool
    {
        foreach ($node->getComments() as $comment) {
            if (preg_match('/@return\s+[^<]*</', $comment->getText())) {
                return true;
            }
        }

        return $this->sourceContainsGenericAnnotationNearMethod($node, '@return');
    }

    private function sourceContainsGenericAnnotationNearMethod(ClassMethod $node, string $tag): bool
    {
        $startLine = $node->getStartLine();
        if ($startLine < 1) {
            return false;
        }

        $fileContent = $this->file->getFileContent();
        $lines = explode("\n", $fileContent);
        $searchStart = max(0, $startLine - 5);
        $searchEnd = min(\count($lines), $startLine + 3);
        $region = implode("\n", \array_slice($lines, $searchStart, $searchEnd - $searchStart));

        return (bool) preg_match('/' . preg_quote($tag, '/') . '\s+[^<\n]*</', $region);
    }

    private function hasGenericParamTag(PhpDocInfo $phpDocInfo, ClassMethod $node, string $paramName): bool
    {
        $paramTags = $phpDocInfo->getTagsByName('@param');
        foreach ($paramTags as $tag) {
            if (! $tag->value instanceof ParamTagValueNode) {
                continue;
            }

            if ($tag->value->parameterName !== $paramName) {
                continue;
            }

            if ($this->typeNodeHasGenericTypes($tag->value->type)) {
                return true;
            }
        }

        return $this->rawCommentsContainGenericParam($node, $paramName);
    }

    private function rawCommentsContainGenericParam(ClassMethod $node, string $paramName): bool
    {
        $escapedName = preg_quote($paramName, '/');
        foreach ($node->getComments() as $comment) {
            if (preg_match('/@param\s+[^<]*<[^>]+>\s+' . $escapedName . '/', $comment->getText())) {
                return true;
            }
        }

        return $this->sourceContainsGenericAnnotationNearMethod($node, '@param');
    }

    private function typeNodeHasGenericTypes(TypeNode $typeNode): bool
    {
        if ($typeNode instanceof GenericTypeNode) {
            return true;
        }

        if ($typeNode instanceof NullableTypeNode) {
            return $this->typeNodeHasGenericTypes($typeNode->type);
        }

        return false;
    }
}
