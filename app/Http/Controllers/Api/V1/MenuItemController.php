<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Actions\CreateMenuItem;
use App\Domain\Catalog\Models\MenuItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use Illuminate\Http\JsonResponse;

class MenuItemController extends Controller
{
    public function index(): JsonResponse
    {
        $menuItems = MenuItem::with('recipeLines.ingredient')->orderBy('name')->get();

        return MenuItemResource::collection($menuItems)->response();
    }

    public function store(StoreMenuItemRequest $request, CreateMenuItem $action): JsonResponse
    {
        $data = $request->validated();
        $menuItem = $action->execute($data['name'], $data['lines']);

        return MenuItemResource::make($menuItem)->response()->setStatusCode(201);
    }
}
