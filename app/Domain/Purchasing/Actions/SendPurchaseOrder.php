<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Exceptions\InvalidOrderStateException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class SendPurchaseOrder
{
    public function execute(int $purchaseOrderId): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrderId) {
            // Same parent-lock discipline as ReceiveDelivery (brief section 6):
            // lock the order row before reading its status, even though a
            // send action is less contended than receiving.
            $order = PurchaseOrder::whereKey($purchaseOrderId)->lockForUpdate()->firstOrFail();

            if ($order->status === PurchaseOrderStatus::Sent) {
                // Sending an already-sent order is a no-op: return its
                // existing representation unchanged.
                return $order->load('lines.ingredient', 'supplier');
            }

            if (! $order->status->canBeSent()) {
                throw new InvalidOrderStateException('send', $order->status->value);
            }

            $order->update(['status' => PurchaseOrderStatus::Sent, 'sent_at' => now()]);

            return $order->load('lines.ingredient', 'supplier');
        });
    }
}
