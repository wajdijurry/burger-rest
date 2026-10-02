<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ingredient_id' => $this->ingredient_id,
            'ingredient_name' => $this->ingredient->name,
            'unit' => $this->ingredient->unit,
            'quantity' => $this->quantity->toString(),
        ];
    }
}
