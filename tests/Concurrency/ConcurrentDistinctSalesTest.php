<?php

namespace Tests\Concurrency;

use App\Domain\Catalog\Actions\CreateIngredient;
use App\Domain\Catalog\Actions\CreateMenuItem;
use App\Domain\Inventory\Models\Sale;
use App\Domain\Shared\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Brief section 9, item 21: "Concurrent distinct sales preserve the total
 * sum of all accepted consumption." Stock movements are pure inserts
 * (append-only ledger), so there is no mutable-balance read-modify-write
 * race to lose - this test proves that empirically with real concurrent
 * processes rather than just asserting it by design.
 */
class ConcurrentDistinctSalesTest extends ConcurrencyTestCase
{
    public function test_ten_concurrent_distinct_sales_all_apply_with_no_lost_updates(): void
    {
        $beef = app(CreateIngredient::class)->execute('Beef', 'g');
        $burger = app(CreateMenuItem::class)->execute('Classic Burger', [
            ['ingredient_id' => $beef->id, 'quantity' => '150'],
        ]);

        $baseUrl = $this->startConcurrentServer();

        $requestCount = 10;
        $eventIds = array_map(fn () => (string) Str::uuid(), range(1, $requestCount));

        $responses = Http::pool(fn ($pool) => array_map(
            fn (string $eventId, int $i) => $pool->as((string) $i)->post("{$baseUrl}/api/v1/sales", [
                'event_id' => $eventId,
                'menu_item_id' => $burger->id,
                'quantity' => 1,
            ]),
            $eventIds,
            array_keys($eventIds),
        ));

        foreach (array_keys($eventIds) as $i) {
            $this->assertSame(201, $responses[(string) $i]->status());
        }

        $this->assertSame($requestCount, Sale::count());

        $totalStock = DB::table('stock_movements')->where('ingredient_id', $beef->id)->sum('quantity');
        // 10 sales x 150g = 1500g consumed -> stock movement total is -1500.
        $this->assertTrue(Quantity::fromString((string) $totalStock)->equals(Quantity::fromString('-1500')));
    }
}
