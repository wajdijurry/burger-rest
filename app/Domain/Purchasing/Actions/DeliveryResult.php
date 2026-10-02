<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Purchasing\Models\Delivery;
use App\Domain\Purchasing\Models\PurchaseOrder;

final class DeliveryResult
{
    public function __construct(
        public readonly Delivery $delivery,
        public readonly PurchaseOrder $order,
        public readonly bool $replayed,
    ) {
    }
}
