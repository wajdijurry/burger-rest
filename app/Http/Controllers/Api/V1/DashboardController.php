<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Inventory\Queries\DashboardQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(DashboardQuery $query): JsonResponse
    {
        $snapshot = $query->snapshot();

        $stock = $snapshot['stock']->map(fn (array $row) => [
            'ingredient_id' => $row['id'],
            'name' => $row['name'],
            'unit' => $row['unit'],
            'quantity' => $row['quantity']->toString(),
            'is_negative' => $row['quantity']->isNegative(),
        ]);

        return response()->json([
            'stock' => $stock,
            'open_orders' => PurchaseOrderResource::collection($snapshot['open_orders']),
            // When this snapshot was taken - not a guarantee of continuous
            // real-time freshness; the UI must keep polling/refreshing (see
            // README "Freshness" section).
            'generated_at' => $snapshot['generated_at']->toISOString(),
        ]);
    }
}
