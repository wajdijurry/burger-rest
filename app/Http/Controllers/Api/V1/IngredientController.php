<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Actions\CreateIngredient;
use App\Domain\Catalog\Models\Ingredient;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIngredientRequest;
use App\Http\Resources\IngredientResource;
use Illuminate\Http\JsonResponse;

class IngredientController extends Controller
{
    public function index(): JsonResponse
    {
        $ingredients = Ingredient::orderBy('name')->get();

        return IngredientResource::collection($ingredients)->response();
    }

    public function store(StoreIngredientRequest $request, CreateIngredient $action): JsonResponse
    {
        $data = $request->validated();
        $ingredient = $action->execute($data['name'], $data['unit']);

        return IngredientResource::make($ingredient)->response()->setStatusCode(201);
    }
}
