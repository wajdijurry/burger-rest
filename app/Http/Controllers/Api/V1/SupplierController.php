<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Actions\CreateSupplier;
use App\Domain\Catalog\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Resources\SupplierResource;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        $suppliers = Supplier::orderBy('name')->get();

        return SupplierResource::collection($suppliers)->response();
    }

    public function store(StoreSupplierRequest $request, CreateSupplier $action): JsonResponse
    {
        $supplier = $action->execute($request->validated()['name']);

        return SupplierResource::make($supplier)->response()->setStatusCode(201);
    }
}
