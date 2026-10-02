<?php

namespace Tests\Concurrency;

use App\Domain\Catalog\Actions\CreateIngredient;
use App\Domain\Catalog\Actions\CreateSupplier;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Domain\Purchasing\Actions\SendPurchaseOrder;
use App\Domain\Purchasing\Models\Delivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Brief section 9, item 20 (delivery half): the same Idempotency-Key with
 * the same payload sent twice, concurrently, for a *valid* (non
 * over-committing) delivery must produce exactly one accepted delivery -
 * normally this is fully serialized by the purchase_orders row lock, but
 * this proves the replay path (not the OVER_RECEIPT path) is what the
 * loser actually hits.
 */
class ConcurrentDeliveryIdempotencyTest extends ConcurrencyTestCase
{
    public function test_two_concurrent_identical_deliveries_produce_exactly_one_delivery(): void
    {
        $supplier = app(CreateSupplier::class)->execute('Acme Foods');
        $ingredient = app(CreateIngredient::class)->execute('Beef', 'g');

        $order = app(CreatePurchaseOrder::class)->execute($supplier->id, [
            ['ingredient_id' => $ingredient->id, 'quantity' => '100'],
        ]);
        $order = app(SendPurchaseOrder::class)->execute($order->id);
        $line = $order->lines->first();

        $baseUrl = $this->startConcurrentServer();

        $key = (string) Str::uuid();
        $payload = ['lines' => [['purchase_order_line_id' => $line->id, 'quantity' => '40']]];

        $responses = Http::pool(fn ($pool) => [
            $pool->as('a')->withHeaders(['Idempotency-Key' => $key])
                ->post("{$baseUrl}/api/v1/purchase-orders/{$order->id}/deliveries", $payload),
            $pool->as('b')->withHeaders(['Idempotency-Key' => $key])
                ->post("{$baseUrl}/api/v1/purchase-orders/{$order->id}/deliveries", $payload),
        ]);

        $statuses = [$responses['a']->status(), $responses['b']->status()];
        sort($statuses);

        $this->assertSame([200, 201], $statuses);
        $this->assertSame(1, Delivery::count());
    }
}
