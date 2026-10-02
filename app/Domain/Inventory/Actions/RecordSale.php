<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\MenuItem;
use App\Domain\Inventory\Exceptions\EmptyRecipeException;
use App\Domain\Inventory\Models\Sale;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Shared\Exceptions\IdempotencyConflictException;
use App\Domain\Shared\RequestHash;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Records a POS sale event and its ingredient deductions.
 *
 * Negative-stock policy (documented assumption, see README): this endpoint
 * represents a completed POS sale that already happened at the till, so it
 * always records the full deduction - including into negative stock - and
 * never clamps or silently skips an ingredient. Pre-sale availability
 * checks are deliberately out of scope and can be added later as an
 * explicit, separate policy.
 *
 * Idempotency: there is no natural "parent row" to lock here the way a
 * purchase order anchors ReceiveDelivery, so duplicate concurrent requests
 * are resolved via the sales.event_id unique constraint rather than a
 * pessimistic lock (brief section 6: "Do not introduce unnecessary
 * ingredient locks to solve a race the model avoids.").
 */
class RecordSale
{
    public function execute(string $eventId, int $menuItemId, int $quantity): SaleResult
    {
        $menuItem = MenuItem::with('recipeLines.ingredient')->findOrFail($menuItemId);

        if ($menuItem->recipeLines->isEmpty()) {
            throw new EmptyRecipeException($menuItemId);
        }

        $requestHash = RequestHash::forSale($menuItemId, $quantity);

        // Fast path: a plain read, no transaction needed, resolves most
        // retries without touching the write path at all.
        if ($existing = Sale::where('event_id', $eventId)->first()) {
            return $this->resolveExisting($existing, $requestHash);
        }

        try {
            return DB::transaction(function () use ($eventId, $menuItem, $quantity, $requestHash) {
                $sale = Sale::create([
                    'event_id' => $eventId,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $quantity,
                    'request_hash' => $requestHash,
                ]);

                foreach ($menuItem->recipeLines as $line) {
                    // Deduction amount is snapshotted now, from today's
                    // recipe; future recipe edits never retroactively change
                    // this movement.
                    $deduction = $line->quantity->multiplyByInteger($quantity)->negate();

                    StockMovement::create([
                        'ingredient_id' => $line->ingredient_id,
                        'quantity' => $deduction,
                        'type' => StockMovement::TYPE_SALE,
                        'sale_id' => $sale->id,
                    ]);
                }

                return new SaleResult($sale, replayed: false);
            });
        } catch (QueryException $e) {
            if (! $this->isSaleEventIdViolation($e)) {
                throw $e;
            }

            // DB::transaction() already rolled back before rethrowing, so
            // this query runs on a clean connection/transaction state, not
            // inside the aborted one.
            $existing = Sale::where('event_id', $eventId)->first();

            if (! $existing) {
                throw $e;
            }

            return $this->resolveExisting($existing, $requestHash);
        }
    }

    private function resolveExisting(Sale $existing, string $requestHash): SaleResult
    {
        if (! hash_equals($existing->request_hash, $requestHash)) {
            throw new IdempotencyConflictException('sale', $existing->event_id);
        }

        return new SaleResult($existing, replayed: true);
    }

    private function isSaleEventIdViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505'
            && str_contains($e->getMessage(), 'sales_event_id_unique');
    }
}
