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

namespace SolidWorx\Platform\SaasBundle\Webhook;

use Override;
use SensitiveParameter;
use SolidWorx\Platform\SaasBundle\Webhook\Converter\LemonSqueezyPayloadConverter;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use function hash_equals;
use function hash_hmac;

final class LemonSqueezyRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly LemonSqueezyPayloadConverter $converter
    ) {
    }

    #[Override]
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new IsJsonRequestMatcher(),
            new MethodRequestMatcher('POST'),
        ]);
    }

    /**
     * @throws JsonException
     */
    #[Override]
    protected function doParse(Request $request, #[SensitiveParameter] string $secret): RemoteEvent
    {
        // Validate the request against $secret.
        $authToken = $request->headers->get('X-Signature');

        $hash = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($hash, (string) $authToken)) {
            throw new RejectWebhookException(Response::HTTP_UNAUTHORIZED, 'Invalid authentication token.');
        }

        // Validate the request payload.
        $inputBag = $request->getPayload();

        if (! $inputBag->has('data') || ! $inputBag->has('meta')) {
            throw new RejectWebhookException(Response::HTTP_BAD_REQUEST, 'Request payload does not contain required fields.');
        }

        // Parse the request payload and return a RemoteEvent object.
        /** @var array{ data: array<string, mixed>, meta: array{ event_name: string, id: string, custom_data?: array{ subscription_id: string }}} $payload */
        $payload = $inputBag->all();

        if (null === ($payload['meta']['custom_data']['subscription_id'] ?? null)) {
            throw new RejectWebhookException(Response::HTTP_BAD_REQUEST, 'Request payload does not contain required fields.');
        }

        try {
            return $this->converter->convert($payload);
        } catch (ParseException $parseException) {
            throw new RejectWebhookException(406, $parseException->getMessage(), $parseException);
        }
    }
}
