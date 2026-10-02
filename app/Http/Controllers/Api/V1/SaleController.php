<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Inventory\Actions\RecordSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordSaleRequest;
use App\Http\Resources\SaleResource;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function store(RecordSaleRequest $request, RecordSale $action): JsonResponse
    {
        $data = $request->validated();

        $result = $action->execute($data['event_id'], $data['menu_item_id'], $data['quantity']);

        return response()->json([
            'sale' => SaleResource::make($result->sale),
            'replayed' => $result->replayed,
        ], $result->replayed ? 200 : 201);
    }
}
