<?php

namespace App\Domain\Inventory\Queries;

use App\Domain\Shared\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Current stock per ingredient, derived from the stock_movements ledger
 * (never a mutable balance column). Ingredients with zero movements are
 * still included via a left join against a pre-aggregated subquery, so a
 * brand-new ingredient reads as zero rather than being missing.
 */
class StockQuery
{
    /** @return Collection<int, array{id: int, name: string, unit: string, quantity: Quantity}> */
    public function all(): Collection
    {
        $movementTotals = DB::table('stock_movements')
            ->select('ingredient_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('ingredient_id');

        return DB::table('ingredients')
            ->leftJoinSub($movementTotals, 'movement_totals', 'movement_totals.ingredient_id', '=', 'ingredients.id')
            ->select(
                'ingredients.id',
                'ingredients.name',
                'ingredients.unit',
                DB::raw('COALESCE(movement_totals.total, 0) as quantity'),
            )
            ->orderBy('ingredients.name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'unit' => $row->unit,
                'quantity' => Quantity::fromString((string) $row->quantity),
            ]);
    }
}
