<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            // "draft" is displayed with an explicit not-yet-sent label so
            // the manager never mistakes it for "sent".
            'status_label' => $this->status->label(),
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->whenLoaded('supplier', fn () => $this->supplier->name),
            'sent_at' => $this->sent_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'lines' => PurchaseOrderLineResource::collection($this->whenLoaded('lines')),
            'deliveries' => DeliveryResource::collection($this->whenLoaded('deliveries')),
        ];
    }
}
