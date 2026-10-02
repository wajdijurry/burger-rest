<?php

namespace Tests\Concurrency;

use App\Domain\Catalog\Actions\CreateIngredient;
use App\Domain\Catalog\Actions\CreateMenuItem;
use App\Domain\Inventory\Models\Sale;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Brief section 6: "Concurrent duplicate requests: one committed sale and
 * one set of deductions." There is no parent row to lock for a brand-new
 * sale (see RecordSale's docblock), so this proves the sales.event_id
 * unique-constraint race path actually resolves correctly under real
 * concurrent processes, not just in the single-process unit test.
 */
class ConcurrentSaleIdempotencyTest extends ConcurrencyTestCase
{
    public function test_two_concurrent_identical_sale_requests_produce_exactly_one_effect(): void
    {
        $beef = app(CreateIngredient::class)->execute('Beef', 'g');
        $burger = app(CreateMenuItem::class)->execute('Classic Burger', [
            ['ingredient_id' => $beef->id, 'quantity' => '150'],
        ]);

        $baseUrl = $this->startConcurrentServer();

        $eventId = (string) Str::uuid();
        $payload = ['event_id' => $eventId, 'menu_item_id' => $burger->id, 'quantity' => 2];

        $responses = Http::pool(fn ($pool) => [
            $pool->as('a')->post("{$baseUrl}/api/v1/sales", $payload),
            $pool->as('b')->post("{$baseUrl}/api/v1/sales", $payload),
        ]);

        $statuses = [$responses['a']->status(), $responses['b']->status()];
        sort($statuses);

        // One created it (201), the other resolved to the same accepted
        // result (200) - never two 201s, never a 500/409 for a genuinely
        // identical retry.
        $this->assertSame([200, 201], $statuses);

        $this->assertSame(1, Sale::where('event_id', $eventId)->count());
        // One recipe line -> exactly one deduction movement, not two.
        $this->assertSame(1, StockMovement::where('type', 'sale')->count());
    }
}
