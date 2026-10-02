<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'lines' => DeliveryLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
