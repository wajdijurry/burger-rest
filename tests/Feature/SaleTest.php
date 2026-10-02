<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Ingredient;
use App\Domain\Catalog\Models\MenuItem;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    private Ingredient $beef;

    private Ingredient $bun;

    private Ingredient $cheese;

    private MenuItem $burger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);
        $this->bun = Ingredient::create(['name' => 'Bun', 'unit' => 'piece']);
        $this->cheese = Ingredient::create(['name' => 'Cheese', 'unit' => 'g']);

        $response = $this->postJson('/api/v1/menu-items', [
            'name' => 'Classic Burger',
            'lines' => [
                ['ingredient_id' => $this->beef->id, 'quantity' => '150'],
                ['ingredient_id' => $this->bun->id, 'quantity' => '1'],
                ['ingredient_id' => $this->cheese->id, 'quantity' => '20'],
            ],
        ])->assertCreated();

        $this->burger = MenuItem::findOrFail($response->json('data.id'));
    }

    private function receiveStock(Ingredient $ingredient, string $quantity): void
    {
        $supplier = Supplier::firstOrCreate(['name' => 'Acme Foods']);

        $order = PurchaseOrder::findOrFail(
            $this->postJson('/api/v1/purchase-orders', [
                'supplier_id' => $supplier->id,
                'lines' => [['ingredient_id' => $ingredient->id, 'quantity' => $quantity]],
            ])->assertCreated()->json('data.id')
        );
        $this->postJson("/api/v1/purchase-orders/{$order->id}/send")->assertOk();
        $line = $order->lines()->first();
        $this->postJson("/api/v1/purchase-orders/{$order->id}/deliveries", [
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => $quantity]],
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    }

    public function test_sale_consumes_every_recipe_ingredient_correctly(): void
    {
        $this->receiveStock($this->beef, '10000');
        $this->receiveStock($this->bun, '40');
        $this->receiveStock($this->cheese, '800');

        $this->postJson('/api/v1/sales', [
            'event_id' => (string) Str::uuid(),
            'menu_item_id' => $this->burger->id,
            'quantity' => 3,
        ])->assertCreated();

        $this->getJson('/api/v1/stock')
            ->assertJsonFragment(['ingredient_id' => $this->beef->id, 'quantity' => '9550.000'])
            ->assertJsonFragment(['ingredient_id' => $this->bun->id, 'quantity' => '37.000'])
            ->assertJsonFragment(['ingredient_id' => $this->cheese->id, 'quantity' => '740.000']);
    }

    public function test_sale_is_accepted_down_to_exactly_zero_stock(): void
    {
        $this->receiveStock($this->beef, '150');
        $this->receiveStock($this->bun, '1');
        $this->receiveStock($this->cheese, '20');

        $this->postJson('/api/v1/sales', [
            'event_id' => (string) Str::uuid(),
            'menu_item_id' => $this->burger->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->getJson('/api/v1/stock')->assertJsonFragment([
            'ingredient_id' => $this->beef->id, 'quantity' => '0.000', 'is_negative' => false,
        ]);
    }

    public function test_sale_is_accepted_below_zero_stock_and_flagged(): void
    {
        // No stock received at all - the policy is to record the sale and
        // its deductions anyway, exposing the discrepancy rather than
        // rejecting or clamping.
        $this->postJson('/api/v1/sales', [
            'event_id' => (string) Str::uuid(),
            'menu_item_id' => $this->burger->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->getJson('/api/v1/stock')->assertJsonFragment([
            'ingredient_id' => $this->beef->id, 'quantity' => '-150.000', 'is_negative' => true,
        ]);
    }

    public function test_sales_do_not_change_purchase_order_outstanding(): void
    {
        $this->receiveStock($this->beef, '10000');
        $order = PurchaseOrder::first();

        // Fully received above, so outstanding is already 0; the point of
        // this test is that selling burgers (which consume beef) does not
        // move this number at all.
        $this->assertSame('0.000', $this->getJson("/api/v1/purchase-orders/{$order->id}")->json('data.lines.0.quantity_outstanding'));

        $this->postJson('/api/v1/sales', [
            'event_id' => (string) Str::uuid(),
            'menu_item_id' => $this->burger->id,
            'quantity' => 5,
        ])->assertCreated();

        $response = $this->getJson("/api/v1/purchase-orders/{$order->id}")->assertOk();
        $this->assertSame('0.000', $response->json('data.lines.0.quantity_outstanding'));
    }

    public function test_duplicate_sale_event_id_with_same_payload_has_exactly_one_effect(): void
    {
        $eventId = (string) Str::uuid();
        $payload = ['event_id' => $eventId, 'menu_item_id' => $this->burger->id, 'quantity' => 2];

        $first = $this->postJson('/api/v1/sales', $payload)->assertCreated();
        $this->assertFalse($first->json('replayed'));

        $second = $this->postJson('/api/v1/sales', $payload)->assertOk();
        $this->assertTrue($second->json('replayed'));

        $this->assertSame(1, \App\Domain\Inventory\Models\Sale::count());
        $this->assertSame(3, StockMovement::where('type', 'sale')->count()); // 3 recipe lines, once each
    }

    public function test_duplicate_sale_event_id_with_different_payload_is_rejected(): void
    {
        $eventId = (string) Str::uuid();

        $this->postJson('/api/v1/sales', [
            'event_id' => $eventId, 'menu_item_id' => $this->burger->id, 'quantity' => 2,
        ])->assertCreated();

        $this->postJson('/api/v1/sales', [
            'event_id' => $eventId, 'menu_item_id' => $this->burger->id, 'quantity' => 3,
        ])->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_rejects_sale_for_menu_item_with_empty_recipe(): void
    {
        $empty = MenuItem::create(['name' => 'Water Cup']);

        $this->postJson('/api/v1/sales', [
            'event_id' => (string) Str::uuid(),
            'menu_item_id' => $empty->id,
            'quantity' => 1,
        ])->assertStatus(422)->assertJsonPath('error.code', 'EMPTY_RECIPE');
    }

    public function test_rejects_sale_quantity_above_ten_thousand(): void
    {
        $this->postJson('/api/v1/sales', [
            'event_id' => (string) Str::uuid(),
            'menu_item_id' => $this->burger->id,
            'quantity' => 10001,
        ])->assertStatus(422);
    }

    public function test_rejects_non_uuid_event_id(): void
    {
        $this->postJson('/api/v1/sales', [
            'event_id' => 'not-a-uuid',
            'menu_item_id' => $this->burger->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    public function test_shared_ingredient_across_recipes_contributes_to_the_same_stock(): void
    {
        $cheeseburger = MenuItem::create(['name' => 'Cheeseburger Deluxe']);
        $cheeseburger->recipeLines()->create(['ingredient_id' => $this->cheese->id, 'quantity' => '40.000']);

        $this->receiveStock($this->cheese, '1000');

        $this->postJson('/api/v1/sales', [
            'event_id' => (string) Str::uuid(), 'menu_item_id' => $this->burger->id, 'quantity' => 2,
        ])->assertCreated(); // -40g cheese

        $this->postJson('/api/v1/sales', [
            'event_id' => (string) Str::uuid(), 'menu_item_id' => $cheeseburger->id, 'quantity' => 1,
        ])->assertCreated(); // -40g cheese

        $this->getJson('/api/v1/stock')
            ->assertJsonFragment(['ingredient_id' => $this->cheese->id, 'quantity' => '920.000']);
    }

    public function test_a_controlled_failure_mid_sale_rolls_back_every_write(): void
    {
        $this->receiveStock($this->beef, '10000');

        StockMovement::creating(function ($model) {
            if ((int) $model->ingredient_id === $this->cheese->id) {
                throw new \RuntimeException('Simulated failure for rollback test.');
            }
        });

        try {
            $this->postJson('/api/v1/sales', [
                'event_id' => (string) Str::uuid(), 'menu_item_id' => $this->burger->id, 'quantity' => 1,
            ]);
        } catch (\Throwable) {
        } finally {
            StockMovement::flushEventListeners();
        }

        $this->assertSame(0, \App\Domain\Inventory\Models\Sale::count());
        $this->assertSame(0, StockMovement::where('type', 'sale')->count());
        $this->getJson('/api/v1/stock')
            ->assertJsonFragment(['ingredient_id' => $this->beef->id, 'quantity' => '10000.000']);
    }
}
