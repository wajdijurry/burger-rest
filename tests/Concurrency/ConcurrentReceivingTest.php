<?php

namespace Tests\Concurrency;

use App\Domain\Catalog\Actions\CreateIngredient;
use App\Domain\Catalog\Actions\CreateSupplier;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Domain\Purchasing\Actions\SendPurchaseOrder;
use App\Domain\Purchasing\Models\Delivery;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use App\Domain\Shared\Quantity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Brief section 6 acceptance case: 6 units outstanding, two distinct
 * concurrent deliveries each request 4. Exactly one may succeed; total
 * accepted receiving must be 4, not 8. This only holds if the parent
 * purchase_orders row lock genuinely serializes the two real, independent
 * HTTP requests/connections below - which is what this test proves.
 */
class ConcurrentReceivingTest extends ConcurrencyTestCase
{
    public function test_exactly_one_of_two_concurrent_over_committing_deliveries_succeeds(): void
    {
        $supplier = app(CreateSupplier::class)->execute('Acme Foods');
        $ingredient = app(CreateIngredient::class)->execute('Beef', 'g');

        $order = app(CreatePurchaseOrder::class)->execute($supplier->id, [
            ['ingredient_id' => $ingredient->id, 'quantity' => '6'],
        ]);
        $order = app(SendPurchaseOrder::class)->execute($order->id);
        $line = $order->lines->first();

        $baseUrl = $this->startConcurrentServer();

        $keyA = (string) Str::uuid();
        $keyB = (string) Str::uuid();
        $payload = ['lines' => [['purchase_order_line_id' => $line->id, 'quantity' => '4']]];

        $responses = Http::pool(fn ($pool) => [
            $pool->as('a')->withHeaders(['Idempotency-Key' => $keyA])
                ->post("{$baseUrl}/api/v1/purchase-orders/{$order->id}/deliveries", $payload),
            $pool->as('b')->withHeaders(['Idempotency-Key' => $keyB])
                ->post("{$baseUrl}/api/v1/purchase-orders/{$order->id}/deliveries", $payload),
        ]);

        $statuses = [$responses['a']->status(), $responses['b']->status()];
        sort($statuses);

        // One accepted (201), one rejected as a domain conflict (409) - never
        // two 201s, never a 500.
        $this->assertSame([201, 409], $statuses);

        $this->assertSame(1, Delivery::count());

        $totalReceived = PurchaseOrderLine::find($line->id)->deliveryLines()->sum('quantity_received');
        $this->assertTrue(Quantity::fromString((string) $totalReceived)->equals(Quantity::fromString('4')));
    }
}
