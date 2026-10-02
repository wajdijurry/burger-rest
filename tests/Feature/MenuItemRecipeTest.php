<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemRecipeTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_valid_recipe(): void
    {
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);
        $bun = Ingredient::create(['name' => 'Bun', 'unit' => 'piece']);

        $response = $this->postJson('/api/v1/menu-items', [
            'name' => 'Classic Burger',
            'lines' => [
                ['ingredient_id' => $beef->id, 'quantity' => '150'],
                ['ingredient_id' => $bun->id, 'quantity' => '1'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Classic Burger')
            ->assertJsonCount(2, 'data.recipe_lines')
            ->assertJsonPath('data.recipe_lines.0.quantity', '150.000');
    }

    public function test_supports_fractional_piece_quantities(): void
    {
        $bun = Ingredient::create(['name' => 'Bun', 'unit' => 'piece']);

        $this->postJson('/api/v1/menu-items', [
            'name' => 'Mini Slider',
            'lines' => [['ingredient_id' => $bun->id, 'quantity' => '0.5']],
        ])->assertCreated()->assertJsonPath('data.recipe_lines.0.quantity', '0.500');
    }

    public function test_rejects_empty_recipe(): void
    {
        $this->postJson('/api/v1/menu-items', ['name' => 'Nothing Burger', 'lines' => []])
            ->assertStatus(422);
    }

    public function test_rejects_unknown_ingredient(): void
    {
        $this->postJson('/api/v1/menu-items', [
            'name' => 'Ghost Burger',
            'lines' => [['ingredient_id' => 999999, 'quantity' => '1']],
        ])->assertStatus(422);
    }

    public function test_rejects_duplicate_ingredient_lines(): void
    {
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $this->postJson('/api/v1/menu-items', [
            'name' => 'Double Beef Burger',
            'lines' => [
                ['ingredient_id' => $beef->id, 'quantity' => '150'],
                ['ingredient_id' => $beef->id, 'quantity' => '50'],
            ],
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_QUANTITY');
    }

    public function test_rejects_more_than_three_decimal_places(): void
    {
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $this->postJson('/api/v1/menu-items', [
            'name' => 'Precise Burger',
            'lines' => [['ingredient_id' => $beef->id, 'quantity' => '150.1234']],
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_QUANTITY');
    }

    public function test_rejects_zero_quantity(): void
    {
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $this->postJson('/api/v1/menu-items', [
            'name' => 'Empty Burger',
            'lines' => [['ingredient_id' => $beef->id, 'quantity' => '0']],
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_QUANTITY');
    }

    public function test_rejects_negative_quantity(): void
    {
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $this->postJson('/api/v1/menu-items', [
            'name' => 'Negative Burger',
            'lines' => [['ingredient_id' => $beef->id, 'quantity' => '-5']],
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_QUANTITY');
    }

    public function test_rejects_quantity_above_bound(): void
    {
        $beef = Ingredient::create(['name' => 'Beef', 'unit' => 'g']);

        $this->postJson('/api/v1/menu-items', [
            'name' => 'Giant Burger',
            'lines' => [['ingredient_id' => $beef->id, 'quantity' => '1000000.001']],
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_QUANTITY');
    }
}
