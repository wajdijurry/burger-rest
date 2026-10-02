<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->event_id,
            'menu_item_id' => $this->menu_item_id,
            'quantity' => $this->quantity,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
