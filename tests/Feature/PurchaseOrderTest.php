<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Ingredient;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_draft_order(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Foods']);
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $response = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'lines' => [['ingredient_id' => $beef->id, 'quantity' => '10000']],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.lines.0.quantity_outstanding', '10000.000');
    }

    public function test_client_cannot_choose_initial_status(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Foods']);
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $response = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'status' => 'closed',
            'lines' => [['ingredient_id' => $beef->id, 'quantity' => '10000']],
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'draft');
    }

    public function test_rejects_missing_supplier(): void
    {
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => 999999,
            'lines' => [['ingredient_id' => $beef->id, 'quantity' => '10000']],
        ])->assertStatus(422);
    }

    public function test_rejects_empty_lines(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Foods']);

        $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'lines' => [],
        ])->assertStatus(422);
    }

    public function test_rejects_duplicate_ingredient_lines(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Foods']);
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'lines' => [
                ['ingredient_id' => $beef->id, 'quantity' => '100'],
                ['ingredient_id' => $beef->id, 'quantity' => '200'],
            ],
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_QUANTITY');
    }

    public function test_rejects_non_positive_quantity(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Foods']);
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'lines' => [['ingredient_id' => $beef->id, 'quantity' => '0']],
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_QUANTITY');
    }

    public function test_sending_a_draft_order_preserves_stock_and_moves_to_sent(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Foods']);
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);
        $order = $this->createDraftOrder($supplier, $beef, '10000');

        $this->postJson("/api/v1/purchase-orders/{$order->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->getJson('/api/v1/stock')
            ->assertOk()
            ->assertJsonFragment(['ingredient_id' => $beef->id, 'quantity' => '0.000']);
    }

    public function test_sending_an_already_sent_order_is_a_no_op(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Foods']);
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);
        $order = $this->createDraftOrder($supplier, $beef, '10000');

        $this->postJson("/api/v1/purchase-orders/{$order->id}/send")->assertOk();

        $this->postJson("/api/v1/purchase-orders/{$order->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');
    }

    public function test_cannot_send_a_received_order(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Foods']);
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);
        $order = $this->createDraftOrder($supplier, $beef, '100');
        $this->postJson("/api/v1/purchase-orders/{$order->id}/send")->assertOk();

        $line = $order->lines()->first();
        $this->postJson("/api/v1/purchase-orders/{$order->id}/deliveries", [
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => '40']],
        ], ['Idempotency-Key' => (string) \Illuminate\Support\Str::uuid()])->assertCreated();

        $this->postJson("/api/v1/purchase-orders/{$order->id}/send")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_ORDER_STATE');
    }

    public function test_cannot_receive_a_draft_order(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Foods']);
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);
        $order = $this->createDraftOrder($supplier, $beef, '100');
        $line = $order->lines()->first();

        $this->postJson("/api/v1/purchase-orders/{$order->id}/deliveries", [
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => '40']],
        ], ['Idempotency-Key' => (string) \Illuminate\Support\Str::uuid()])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_ORDER_STATE');
    }

    public function test_lists_open_orders_excluding_closed(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Foods']);
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $openOrder = $this->createDraftOrder($supplier, $beef, '100');

        $closedOrder = $this->createDraftOrder($supplier, $beef, '50');
        $this->postJson("/api/v1/purchase-orders/{$closedOrder->id}/send")->assertOk();
        $closedLine = $closedOrder->lines()->first();
        $this->postJson("/api/v1/purchase-orders/{$closedOrder->id}/deliveries", [
            'lines' => [['purchase_order_line_id' => $closedLine->id, 'quantity' => '50']],
        ], ['Idempotency-Key' => (string) \Illuminate\Support\Str::uuid()])->assertCreated();

        $response = $this->getJson('/api/v1/purchase-orders?status=open')->assertOk();
        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($openOrder->id, $ids);
        $this->assertNotContains($closedOrder->id, $ids);
    }

    private function createDraftOrder(Supplier $supplier, Ingredient $ingredient, string $quantity): PurchaseOrder
    {
        $response = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'lines' => [['ingredient_id' => $ingredient->id, 'quantity' => $quantity]],
        ])->assertCreated();

        return PurchaseOrder::findOrFail($response->json('data.id'));
    }
}
