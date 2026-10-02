<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Domain\Purchasing\Actions\SendPurchaseOrder;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    /**
     * ?status=open      -> every non-closed order (draft/sent/received),
     *                       i.e. the "open orders" view from the brief.
     * ?status=draft|sent|received|closed -> that exact status.
     * (no filter)       -> every order, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['supplier', 'lines.ingredient', 'lines.deliveryLines'])
            ->orderByDesc('created_at');

        $status = $request->query('status');

        if ($status === 'open') {
            $query->whereIn('status', array_map(
                fn (PurchaseOrderStatus $s) => $s->value,
                array_filter(PurchaseOrderStatus::cases(), fn ($s) => $s->isOpen()),
            ));
        } elseif (is_string($status) && PurchaseOrderStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        return PurchaseOrderResource::collection($query->get())->response();
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrder $action): JsonResponse
    {
        $data = $request->validated();
        $order = $action->execute($data['supplier_id'], $data['lines']);

        return PurchaseOrderResource::make($order)->response()->setStatusCode(201);
    }

    public function show(int $purchaseOrder): JsonResponse
    {
        $order = PurchaseOrder::with(['supplier', 'lines.ingredient', 'lines.deliveryLines', 'deliveries.lines.purchaseOrderLine.ingredient'])
            ->findOrFail($purchaseOrder);

        return PurchaseOrderResource::make($order)->response();
    }

    public function send(int $purchaseOrder, SendPurchaseOrder $action): JsonResponse
    {
        $order = $action->execute($purchaseOrder);

        return PurchaseOrderResource::make($order)->response();
    }
}
