<?php

namespace App\Domain\Inventory\Queries;

use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One consistent read of stock + open orders for the dashboard.
 *
 * "Consistent" here means: both reads see the same database snapshot, via
 * a REPEATABLE READ, read-only transaction configured *before* its first
 * query (brief section 7). Several ordinary sequential reads would not give
 * this guarantee on their own, since a write could land between them.
 *
 * This is a visibility guarantee for the instant the snapshot was taken,
 * not a promise that the returned data stays fresh afterwards - the
 * generated_at timestamp tells the client exactly when that was; the UI is
 * responsible for polling/refreshing to stay current (see freshness notes
 * in README).
 */
class DashboardQuery
{
    public function __construct(private readonly StockQuery $stockQuery) {}

    public function snapshot(): array
    {
        return DB::transaction(function () {
            // Only a genuinely top-level transaction can set its isolation
            // level in PostgreSQL. If a caller already has one open (e.g.
            // tests wrapping each test in a transaction via
            // RefreshDatabase), DB::transaction() opens a nested
            // SAVEPOINT instead, and transactionLevel() will be > 1 here -
            // in that case the already-open transaction's isolation level
            // governs, which is a strictly *stronger* consistency guarantee
            // for a single test than this method is asking for anyway.
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }

            $stock = $this->stockQuery->all();

            $openStatuses = array_map(
                fn (PurchaseOrderStatus $s) => $s->value,
                array_filter(PurchaseOrderStatus::cases(), fn ($s) => $s->isOpen()),
            );

            $openOrders = PurchaseOrder::with(['supplier', 'lines.ingredient', 'lines.deliveryLines'])
                ->whereIn('status', $openStatuses)
                ->orderBy('created_at')
                ->get();

            return [
                'stock' => $stock,
                'open_orders' => $openOrders,
                'generated_at' => Carbon::now(),
            ];
        });
    }
}
