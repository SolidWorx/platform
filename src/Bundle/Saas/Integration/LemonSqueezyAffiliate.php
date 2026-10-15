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

namespace SolidWorx\Platform\SaasBundle\Integration;

use Symfony\Component\HttpFoundation\Request;

/**
 * Lemon Squeezy affiliate click id ("aff_ref").
 *
 * https://lmsqueezy.com/affiliate.js tracks a `?aff=<code>` visit, stores the
 * resulting click id in the `ls_aff_ref` cookie and appends `?aff_ref=<click id>`
 * to links pointing at the configured domains. A cookie can't cross from the
 * marketing domain to the app domain, so the app picks the click id up from the
 * `aff_ref` query parameter and keeps it in its own `ls_aff_ref` cookie.
 */
final class LemonSqueezyAffiliate
{
    public const string COOKIE = 'ls_aff_ref';

    public const string QUERY_PARAMETER = 'aff_ref';

    public static function isValid(mixed $ref): bool
    {
        return is_string($ref) && preg_match('/^[A-Za-z0-9_-]{1,128}$/', $ref) === 1;
    }

    public static function fromCookie(?Request $request): ?string
    {
        $ref = $request?->cookies->get(self::COOKIE);

        return self::isValid($ref) ? $ref : null;
    }
}
