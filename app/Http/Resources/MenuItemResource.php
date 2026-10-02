<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'recipe_lines' => RecipeLineResource::collection($this->whenLoaded('recipeLines')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
