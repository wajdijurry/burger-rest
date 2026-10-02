<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Purchasing\Actions\ReceiveDelivery;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveDeliveryRequest;
use App\Http\Resources\DeliveryResource;
use App\Http\Resources\PurchaseOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    public function store(int $purchaseOrder, ReceiveDeliveryRequest $request, ReceiveDelivery $action): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || ! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The Idempotency-Key header is required and must be a UUID.',
            ]);
        }

        $result = $action->execute($purchaseOrder, $idempotencyKey, $request->validated()['lines']);

        return response()->json([
            'delivery' => DeliveryResource::make($result->delivery),
            'purchase_order' => PurchaseOrderResource::make($result->order->load(['supplier', 'lines.ingredient', 'lines.deliveryLines'])),
            'replayed' => $result->replayed,
        ], $result->replayed ? 200 : 201);
    }
}
