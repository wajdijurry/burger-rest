<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_listing_includes_ingredients_with_zero_movements(): void
    {
        Ingredient::create(['name' => 'Beef', 'unit' => 'g']);
        Ingredient::create(['name' => 'Unused Spice', 'unit' => 'g']);

        $response = $this->getJson('/api/v1/stock')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $response->assertJsonFragment(['name' => 'Unused Spice', 'quantity' => '0.000']);
    }

    public function test_dashboard_returns_stock_open_orders_and_generated_at(): void
    {
        Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $response->assertJsonStructure(['stock', 'open_orders', 'generated_at']);
        $this->assertNotEmpty($response->json('generated_at'));
    }
}
