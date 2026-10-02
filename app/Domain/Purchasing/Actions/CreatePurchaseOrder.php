<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Catalog\Models\Ingredient;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Shared\Exceptions\InvalidQuantityException;
use App\Domain\Shared\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrder
{
    /**
     * @param  array<int, array{ingredient_id: int, quantity: string}>  $lines
     */
    public function execute(int $supplierId, array $lines): PurchaseOrder
    {
        if (! Supplier::whereKey($supplierId)->exists()) {
            throw ValidationException::withMessages(['supplier_id' => 'The selected supplier does not exist.']);
        }

        if ($lines === []) {
            throw new InvalidQuantityException('A purchase order must have at least one ingredient line.', 'lines');
        }

        $ingredientIds = array_column($lines, 'ingredient_id');
        if (count($ingredientIds) !== count(array_unique($ingredientIds))) {
            throw new InvalidQuantityException('Each ingredient may appear only once per order.', 'lines');
        }

        $existingCount = Ingredient::whereIn('id', $ingredientIds)->count();
        if ($existingCount !== count($ingredientIds)) {
            throw new InvalidQuantityException('One or more ingredient IDs do not exist.', 'lines');
        }

        $parsedLines = array_map(
            fn (array $line) => [
                'ingredient_id' => $line['ingredient_id'],
                'quantity' => Quantity::fromString((string) $line['quantity'])
                    ->assertPositive('lines')
                    ->assertWithinBound('lines'),
            ],
            $lines,
        );

        return DB::transaction(function () use ($supplierId, $parsedLines) {
            // New orders always start as draft; the client cannot choose an
            // initial status.
            $order = PurchaseOrder::create([
                'supplier_id' => $supplierId,
                'status' => PurchaseOrderStatus::Draft,
            ]);

            foreach ($parsedLines as $line) {
                $order->lines()->create([
                    'ingredient_id' => $line['ingredient_id'],
                    'quantity_ordered' => $line['quantity'],
                ]);
            }

            return $order->load('lines.ingredient', 'supplier');
        });
    }
}
