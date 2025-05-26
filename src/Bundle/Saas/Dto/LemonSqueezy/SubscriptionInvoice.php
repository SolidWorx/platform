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

namespace SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy;

class SubscriptionInvoice
{
    public string $type;

    public string $id;

    public function __construct(
        public SubscriptionInvoiceAttributes $attributes = new SubscriptionInvoiceAttributes(),
        public SubscriptionRelationships $relationships = new SubscriptionRelationships(),
        public SubscriptionLinks $links = new SubscriptionLinks(),
    ) {
    }
}
