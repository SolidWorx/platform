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

use Symfony\Component\Serializer\Attribute\SerializedName;

class SubscriptionRelationships
{
    public function __construct(
        public RelationshipLinks $store = new RelationshipLinks(),
        public RelationshipLinks $customer = new RelationshipLinks(),
        public RelationshipLinks $order = new RelationshipLinks(),
        #[SerializedName('order_item')]
        public RelationshipLinks $orderItem = new RelationshipLinks(),
        public RelationshipLinks $product = new RelationshipLinks(),
        public RelationshipLinks $variant = new RelationshipLinks(),
        #[SerializedName('subscription_items')]
        public RelationshipLinks $subscriptionItems = new RelationshipLinks(),
        #[SerializedName('subscription_invoices')]
        public RelationshipLinks $subscriptionInvoices = new RelationshipLinks(),
    ) {
    }
}
