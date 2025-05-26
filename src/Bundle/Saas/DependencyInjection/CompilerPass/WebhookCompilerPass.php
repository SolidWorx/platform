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

namespace SolidWorx\Platform\SaasBundle\DependencyInjection\CompilerPass;

use Override;
use SolidWorx\Platform\SaasBundle\Webhook\LemonSqueezyRequestParser;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class WebhookCompilerPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('solidworx_platform.saas.integration.payment.lemon_squeezy.webhook_secret')) {
            return;
        }

        if (! $container->hasDefinition('webhook.controller')) {
            return;
        }

        $webhookController = $container->getDefinition('webhook.controller');
        $parsers = $webhookController->getArgument(0);

        if (! is_array($parsers)) {
            $parsers = [];
        }

        $parsers['lemon_squeezy'] = [
            'parser' => new Reference(LemonSqueezyRequestParser::class),
            'secret' => $container->getParameter('solidworx_platform.saas.integration.payment.lemon_squeezy.webhook_secret'),
        ];
        $webhookController->setArgument(0, $parsers);
    }
}
