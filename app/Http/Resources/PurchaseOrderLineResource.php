<?php

namespace App\Http\Resources;

use App\Domain\Shared\Quantity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // deliveryLines is eager-loaded by the caller (never lazy-loaded per
        // row) specifically to avoid N+1s and to avoid a row-multiplying SQL
        // join; the sum is computed here in PHP from the already-fetched,
        // separately-aggregated collection.
        $received = $this->deliveryLines->reduce(
            fn (Quantity $carry, $line) => $carry->add($line->quantity_received),
            Quantity::zero(),
        );

        return [
            'id' => $this->id,
            'ingredient_id' => $this->ingredient_id,
            'ingredient_name' => $this->ingredient->name,
            'unit' => $this->ingredient->unit,
            'quantity_ordered' => $this->quantity_ordered->toString(),
            'quantity_received' => $received->toString(),
            'quantity_outstanding' => $this->quantity_ordered->subtract($received)->toString(),
        ];
    }
}
