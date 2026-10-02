<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Inventory\Queries\StockQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    public function index(StockQuery $query): JsonResponse
    {
        $stock = $query->all()->map(fn (array $row) => [
            'ingredient_id' => $row['id'],
            'name' => $row['name'],
            'unit' => $row['unit'],
            'quantity' => $row['quantity']->toString(),
            // Negative stock is a deliberate, visible policy outcome (see
            // README) - surfaced explicitly rather than left for the UI to
            // infer from a bare sign.
            'is_negative' => $row['quantity']->isNegative(),
        ]);

        return response()->json(['data' => $stock]);
    }
}
