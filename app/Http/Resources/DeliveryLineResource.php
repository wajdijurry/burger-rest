<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'ingredient_name' => $this->purchaseOrderLine->ingredient->name,
            'unit' => $this->purchaseOrderLine->ingredient->unit,
            'quantity_received' => $this->quantity_received->toString(),
        ];
    }
}
