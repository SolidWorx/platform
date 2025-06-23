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

namespace SolidWorx\Platform\PlatformBundle\Validator\Constraint;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class TwoFactorCode extends Constraint
{
    public const string INVALID_CODE_ERROR = '01978d55-112b-72ae-a336-9e1bc05d0f1c';

    protected const array ERROR_NAMES = [
        self::INVALID_CODE_ERROR => 'INVALID_CODE_ERROR',
    ];

    #[HasNamedArguments]
    public function __construct(
        public string $message = 'The code is invalid',
        public string $secret = '',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
