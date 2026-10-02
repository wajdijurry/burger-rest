<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Ingredient;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchasing\Models\Delivery;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReceivingTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Ingredient $beef;

    private Ingredient $bun;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = Supplier::create(['name' => 'Acme Foods']);
        $this->beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);
        $this->bun = Ingredient::create(['name' => 'Bun', 'unit' => 'piece']);
    }

    private function deliver(PurchaseOrder $order, array $lines, ?string $key = null): TestResponse
    {
        return $this->postJson(
            "/api/v1/purchase-orders/{$order->id}/deliveries",
            ['lines' => $lines],
            ['Idempotency-Key' => $key ?? (string) Str::uuid()],
        );
    }

    private function sentOrder(array $lineQuantities): PurchaseOrder
    {
        $lines = [];
        foreach ($lineQuantities as $ingredientId => $qty) {
            $lines[] = ['ingredient_id' => $ingredientId, 'quantity' => $qty];
        }

        $response = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => $lines,
        ])->assertCreated();

        $order = PurchaseOrder::findOrFail($response->json('data.id'));
        $this->postJson("/api/v1/purchase-orders/{$order->id}/send")->assertOk();

        return $order;
    }

    public function test_first_partial_receiving_produces_received_status_and_correct_stock(): void
    {
        $order = $this->sentOrder([$this->beef->id => '10000']);
        $line = $order->lines()->first();

        $response = $this->deliver($order, [['purchase_order_line_id' => $line->id, 'quantity' => '4000']]);

        $response->assertCreated()
            ->assertJsonPath('purchase_order.status', 'received')
            ->assertJsonPath('purchase_order.lines.0.quantity_outstanding', '6000.000');

        $this->getJson('/api/v1/stock')
            ->assertJsonFragment(['ingredient_id' => $this->beef->id, 'quantity' => '4000.000']);
    }

    public function test_successive_receipts_accumulate(): void
    {
        $order = $this->sentOrder([$this->beef->id => '10000']);
        $line = $order->lines()->first();

        $this->deliver($order, [['purchase_order_line_id' => $line->id, 'quantity' => '4000']])->assertCreated();
        $this->deliver($order, [['purchase_order_line_id' => $line->id, 'quantity' => '3000']])
            ->assertCreated()
            ->assertJsonPath('purchase_order.lines.0.quantity_received', '7000.000')
            ->assertJsonPath('purchase_order.lines.0.quantity_outstanding', '3000.000')
            ->assertJsonPath('purchase_order.status', 'received');
    }

    public function test_one_fully_received_line_does_not_close_a_multi_line_order(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100', $this->bun->id => '40']);
        $beefLine = $order->lines()->where('ingredient_id', $this->beef->id)->first();

        $this->deliver($order, [['purchase_order_line_id' => $beefLine->id, 'quantity' => '100']])
            ->assertCreated()
            ->assertJsonPath('purchase_order.status', 'received');
    }

    public function test_final_receiving_closes_the_order(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100', $this->bun->id => '40']);
        $beefLine = $order->lines()->where('ingredient_id', $this->beef->id)->first();
        $bunLine = $order->lines()->where('ingredient_id', $this->bun->id)->first();

        $this->deliver($order, [
            ['purchase_order_line_id' => $beefLine->id, 'quantity' => '100'],
            ['purchase_order_line_id' => $bunLine->id, 'quantity' => '40'],
        ])->assertCreated()->assertJsonPath('purchase_order.status', 'closed');
    }

    public function test_full_first_delivery_jumps_straight_to_closed(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100']);
        $line = $order->lines()->first();

        $this->deliver($order, [['purchase_order_line_id' => $line->id, 'quantity' => '100']])
            ->assertCreated()
            ->assertJsonPath('purchase_order.status', 'closed');
    }

    public function test_rejects_over_receiving_the_whole_delivery_with_no_partial_effect(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100', $this->bun->id => '40']);
        $beefLine = $order->lines()->where('ingredient_id', $this->beef->id)->first();
        $bunLine = $order->lines()->where('ingredient_id', $this->bun->id)->first();

        $this->deliver($order, [
            ['purchase_order_line_id' => $beefLine->id, 'quantity' => '50'], // valid
            ['purchase_order_line_id' => $bunLine->id, 'quantity' => '999'], // over
        ])->assertStatus(409)->assertJsonPath('error.code', 'OVER_RECEIPT');

        // No partial effect: beef line (which was valid on its own) got no stock either.
        $this->getJson('/api/v1/stock')
            ->assertJsonFragment(['ingredient_id' => $this->beef->id, 'quantity' => '0.000'])
            ->assertJsonFragment(['ingredient_id' => $this->bun->id, 'quantity' => '0.000']);

        $order->refresh();
        $this->assertSame('sent', $order->status->value);
    }

    public function test_rejects_cross_order_line_reference(): void
    {
        $orderA = $this->sentOrder([$this->beef->id => '100']);
        $orderB = $this->sentOrder([$this->bun->id => '40']);
        $lineFromOrderB = $orderB->lines()->first();

        $this->deliver($orderA, [['purchase_order_line_id' => $lineFromOrderB->id, 'quantity' => '10']])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'LINE_NOT_IN_ORDER');
    }

    public function test_rejects_duplicate_line_ids_within_one_delivery(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100']);
        $line = $order->lines()->first();

        $this->deliver($order, [
            ['purchase_order_line_id' => $line->id, 'quantity' => '10'],
            ['purchase_order_line_id' => $line->id, 'quantity' => '20'],
        ])->assertStatus(422);
    }

    public function test_rejects_empty_delivery(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100']);

        $this->deliver($order, [])->assertStatus(422);
    }

    public function test_rejects_non_positive_delivery_quantity(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100']);
        $line = $order->lines()->first();

        $this->deliver($order, [['purchase_order_line_id' => $line->id, 'quantity' => '0']])
            ->assertStatus(422);
    }

    public function test_replaying_the_delivery_that_closed_the_order_succeeds(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100']);
        $line = $order->lines()->first();
        $key = (string) Str::uuid();

        $first = $this->deliver($order, [['purchase_order_line_id' => $line->id, 'quantity' => '100']], $key)
            ->assertCreated();
        $this->assertSame('closed', $first->json('purchase_order.status'));

        $replay = $this->deliver($order, [['purchase_order_line_id' => $line->id, 'quantity' => '100']], $key)
            ->assertOk();
        $this->assertSame('closed', $replay->json('purchase_order.status'));
        $this->assertTrue($replay->json('replayed'));

        // Still exactly one delivery, one movement.
        $this->assertSame(1, $order->deliveries()->count());
    }

    public function test_same_idempotency_key_different_payload_is_rejected(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100']);
        $line = $order->lines()->first();
        $key = (string) Str::uuid();

        $this->deliver($order, [['purchase_order_line_id' => $line->id, 'quantity' => '40']], $key)
            ->assertCreated();

        $this->deliver($order, [['purchase_order_line_id' => $line->id, 'quantity' => '41']], $key)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_decimal_canonicalization_and_reordered_lines_replay_correctly(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100', $this->bun->id => '40']);
        $beefLine = $order->lines()->where('ingredient_id', $this->beef->id)->first();
        $bunLine = $order->lines()->where('ingredient_id', $this->bun->id)->first();
        $key = (string) Str::uuid();

        $this->deliver($order, [
            ['purchase_order_line_id' => $beefLine->id, 'quantity' => '40.000'],
            ['purchase_order_line_id' => $bunLine->id, 'quantity' => '10.000'],
        ], $key)->assertCreated();

        // Same operation: lines reordered and "40" instead of "40.000".
        $replay = $this->deliver($order, [
            ['purchase_order_line_id' => $bunLine->id, 'quantity' => '10'],
            ['purchase_order_line_id' => $beefLine->id, 'quantity' => '40'],
        ], $key)->assertOk();

        $this->assertTrue($replay->json('replayed'));
        $this->assertSame(1, $order->deliveries()->count());
    }

    public function test_multi_line_delivery_with_invalid_line_leaves_no_partial_effects(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100', $this->bun->id => '40']);
        $beefLine = $order->lines()->where('ingredient_id', $this->beef->id)->first();
        $otherOrder = $this->sentOrder([$this->bun->id => '5']);
        $foreignLine = $otherOrder->lines()->first();

        $this->deliver($order, [
            ['purchase_order_line_id' => $beefLine->id, 'quantity' => '50'],
            ['purchase_order_line_id' => $foreignLine->id, 'quantity' => '1'],
        ])->assertStatus(422);

        $this->getJson('/api/v1/stock')
            ->assertJsonFragment(['ingredient_id' => $this->beef->id, 'quantity' => '0.000']);
        $this->assertSame(0, Delivery::count());
    }

    public function test_a_controlled_failure_mid_delivery_rolls_back_every_write(): void
    {
        $order = $this->sentOrder([$this->beef->id => '100', $this->bun->id => '40']);
        $beefLine = $order->lines()->where('ingredient_id', $this->beef->id)->first();
        $bunLine = $order->lines()->where('ingredient_id', $this->bun->id)->first();

        // Test-only fault seam: an Eloquent model event, not a production
        // endpoint, used to simulate a failure after the first line's
        // movement has been created but before the transaction commits.
        StockMovement::creating(function ($model) {
            if ((int) $model->ingredient_id === $this->bun->id) {
                throw new \RuntimeException('Simulated failure for rollback test.');
            }
        });

        try {
            $this->deliver($order, [
                ['purchase_order_line_id' => $beefLine->id, 'quantity' => '50'],
                ['purchase_order_line_id' => $bunLine->id, 'quantity' => '10'],
            ]);
        } catch (\Throwable) {
            // The test double-checks via direct DB state below regardless of
            // whether the exception bubbled through the HTTP kernel or was
            // caught by Laravel's handler.
        } finally {
            StockMovement::flushEventListeners();
        }

        $this->assertSame(0, Delivery::count());
        $this->assertSame(0, PurchaseOrderLine::whereHas('deliveryLines')->count());
        $this->assertSame(0, StockMovement::count());
        $order->refresh();
        $this->assertSame('sent', $order->status->value);
    }
}
