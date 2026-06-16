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
use SolidWorx\Platform\PlatformBundle\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function file_put_contents;
use function sprintf;

#[AsCommand(
    name: 'platform:disposable-domains:update',
    description: 'Refreshes the supplemental disposable email domains blocklist from the upstream source.',
)]
final class UpdateDisposableDomainsCommand extends Command
{
    private const string SOURCE_URL = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/master/disposable_email_blocklist.conf';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $blocklistFile,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function handle(): int
    {
        $this->io->title('Refreshing disposable email domains blocklist');

        $response = $this->httpClient->request('GET', self::SOURCE_URL);
        $content = $response->getContent();

        file_put_contents($this->blocklistFile, $content);

        $this->io->success(sprintf('Disposable email domains blocklist refreshed: %s', $this->blocklistFile));

        return self::SUCCESS;
    }
}
