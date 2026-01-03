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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Twig\Extension\FeatureExtension;
use SolidWorx\Platform\SaasBundle\Twig\Runtime\FeatureRuntime;
use Twig\TwigFunction;

#[CoversClass(FeatureExtension::class)]
final class FeatureExtensionTest extends TestCase
{
    private FeatureExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new FeatureExtension();
    }

    public function testGetFunctionsReturnsExpectedFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        self::assertCount(5, $functions);
        self::assertContainsOnlyInstancesOf(TwigFunction::class, $functions);
    }

    public function testHasFeatureFunctionIsRegistered(): void
    {
        $functions = $this->getFunctionsByName();

        self::assertArrayHasKey('has_feature', $functions);
        self::assertSame([FeatureRuntime::class, 'hasFeature'], $functions['has_feature']->getCallable());
    }

    public function testFeatureValueFunctionIsRegistered(): void
    {
        $functions = $this->getFunctionsByName();

        self::assertArrayHasKey('feature_value', $functions);
        self::assertSame([FeatureRuntime::class, 'getFeatureValue'], $functions['feature_value']->getCallable());
    }

    public function testCanUseFeatureFunctionIsRegistered(): void
    {
        $functions = $this->getFunctionsByName();

        self::assertArrayHasKey('can_use_feature', $functions);
        self::assertSame([FeatureRuntime::class, 'canUseFeature'], $functions['can_use_feature']->getCallable());
    }

    public function testFeatureRemainingFunctionIsRegistered(): void
    {
        $functions = $this->getFunctionsByName();

        self::assertArrayHasKey('feature_remaining', $functions);
        self::assertSame([FeatureRuntime::class, 'getRemainingQuota'], $functions['feature_remaining']->getCallable());
    }

    public function testIsFeatureUnlimitedFunctionIsRegistered(): void
    {
        $functions = $this->getFunctionsByName();

        self::assertArrayHasKey('is_feature_unlimited', $functions);
        self::assertSame([FeatureRuntime::class, 'isUnlimited'], $functions['is_feature_unlimited']->getCallable());
    }

    /**
     * @return array<string, TwigFunction>
     */
    private function getFunctionsByName(): array
    {
        $functions = [];
        foreach ($this->extension->getFunctions() as $function) {
            $functions[$function->getName()] = $function;
        }

        return $functions;
    }
}
