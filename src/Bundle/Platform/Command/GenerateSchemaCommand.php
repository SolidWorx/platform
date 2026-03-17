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

namespace SolidWorx\Platform\PlatformBundle\Command;

use Override;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\PlanFeature;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\SubscriptionLog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function array_merge;
use function is_string;
use function json_encode;
use function sprintf;

#[AsCommand(
    name: 'platform:generate-schema',
    description: 'Generates the JSON Schema for platform.yaml IDE autocompletion.',
)]
final class GenerateSchemaCommand extends Command
{
    private const string DEFAULT_OUTPUT = 'platform-schema.json';

    #[Override]
    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', self::DEFAULT_OUTPUT);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $outputFile = $input->getOption('output');
        if (! is_string($outputFile)) {
            $outputFile = self::DEFAULT_OUTPUT;
        }

        $schema = $this->buildSchema();

        $json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        file_put_contents($outputFile, $json);

        $io->success(sprintf('JSON Schema written to %s', $outputFile));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'title' => 'SolidWorx Platform Configuration',
            'description' => 'Configuration schema for platform.yaml',
            'type' => 'object',
            'properties' => [
                'platform' => $this->buildPlatformSchema(),
            ],
            'additionalProperties' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPlatformSchema(): array
    {
        return [
            'type' => 'object',
            'description' => 'Core platform configuration',
            'properties' => array_merge(
                $this->buildCoreProperties(),
                $this->buildSaasProperties(),
                $this->buildUiProperties(),
            ),
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCoreProperties(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'description' => 'The name of the platform.',
                'default' => 'SolidWorx Platform',
            ],
            'version' => [
                'type' => 'string',
                'description' => 'The version of the platform.',
                'default' => '1.0.0',
            ],
            'security' => [
                'type' => 'object',
                'properties' => [
                    'two_factor' => [
                        'type' => 'object',
                        'properties' => [
                            'enabled' => [
                                'type' => 'boolean',
                                'description' => 'Enable two-factor authentication.',
                                'default' => false,
                            ],
                            'base_template' => [
                                'type' => ['string', 'null'],
                                'description' => 'The base layout template for 2FA pages.',
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'additionalProperties' => false,
            ],
            'doctrine' => [
                'type' => 'object',
                'properties' => [
                    'types' => [
                        'type' => 'object',
                        'properties' => [
                            'enable_utc_date' => [
                                'type' => 'boolean',
                                'description' => 'Enable UTC date type.',
                                'default' => true,
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'additionalProperties' => false,
            ],
            'models' => [
                'type' => 'object',
                'properties' => [
                    'user' => [
                        'type' => 'string',
                        'description' => 'The User model class.',
                        'default' => User::class,
                    ],
                ],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSaasProperties(): array
    {
        return [
            'saas' => [
                'type' => 'object',
                'description' => 'SaaS / subscription configuration',
                'properties' => [
                    'doctrine' => [
                        'type' => 'object',
                        'required' => ['subscriptions'],
                        'properties' => [
                            'subscriptions' => [
                                'type' => 'object',
                                'required' => ['entity'],
                                'properties' => [
                                    'entity' => [
                                        'type' => 'string',
                                        'description' => 'Fully-qualified class name of the subscription entity.',
                                    ],
                                ],
                            ],
                            'db_schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'table_names' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'plan' => [
                                                'type' => 'string',
                                                'default' => Plan::TABLE_NAME,
                                            ],
                                            'subscription' => [
                                                'type' => 'string',
                                                'default' => Subscription::TABLE_NAME,
                                            ],
                                            'subscription_log' => [
                                                'type' => 'string',
                                                'default' => SubscriptionLog::TABLE_NAME,
                                            ],
                                            'plan_feature' => [
                                                'type' => 'string',
                                                'default' => PlanFeature::TABLE_NAME,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'payment' => [
                        'type' => 'object',
                        'required' => ['return_route'],
                        'properties' => [
                            'return_route' => [
                                'type' => 'string',
                                'description' => 'The route name to redirect to after payment.',
                            ],
                        ],
                    ],
                    'integration' => [
                        'type' => 'object',
                        'properties' => [
                            'lemon_squeezy' => [
                                'type' => 'object',
                                'properties' => [
                                    'enabled' => [
                                        'type' => 'boolean',
                                        'default' => false,
                                    ],
                                    'api_key' => [
                                        'type' => 'string',
                                    ],
                                    'webhook_secret' => [
                                        'type' => 'string',
                                    ],
                                    'store_id' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'features' => [
                        'type' => 'object',
                        'description' => 'Named feature definitions.',
                        'additionalProperties' => [
                            'type' => 'object',
                            'required' => ['type', 'default'],
                            'properties' => [
                                'type' => [
                                    'type' => 'string',
                                    'enum' => ['boolean', 'integer', 'string', 'array'],
                                ],
                                'default' => [
                                    'description' => 'Default value for the feature.',
                                ],
                                'description' => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUiProperties(): array
    {
        return [
            'ui' => [
                'type' => 'object',
                'description' => 'UI / presentation configuration',
                'properties' => [
                    'icon_pack' => [
                        'type' => 'string',
                        'description' => 'The icon pack to use.',
                        'default' => 'tabler',
                    ],
                    'templates' => [
                        'type' => 'object',
                        'properties' => [
                            'base' => [
                                'type' => 'string',
                                'description' => 'The base layout template.',
                                'default' => '@Ui/Layout/base.html.twig',
                            ],
                            'login' => [
                                'type' => 'string',
                                'description' => 'The login page template.',
                                'default' => '@Ui/Security/login.html.twig',
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'additionalProperties' => false,
            ],
        ];
    }
}
