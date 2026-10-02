<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\IngredientController;
use App\Http\Controllers\Api\V1\MenuItemController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('ingredients', [IngredientController::class, 'index']);
    Route::post('ingredients', [IngredientController::class, 'store']);

    Route::get('suppliers', [SupplierController::class, 'index']);
    Route::post('suppliers', [SupplierController::class, 'store']);

    Route::get('menu-items', [MenuItemController::class, 'index']);
    Route::post('menu-items', [MenuItemController::class, 'store']);

    Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    Route::post('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send']);
    Route::post('purchase-orders/{purchaseOrder}/deliveries', [DeliveryController::class, 'store']);

    Route::post('sales', [SaleController::class, 'store']);

    Route::get('stock', [StockController::class, 'index']);

    Route::get('dashboard', [DashboardController::class, 'index']);
});
