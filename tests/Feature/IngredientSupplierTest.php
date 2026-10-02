<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientSupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_and_lists_ingredients(): void
    {
        $this->postJson('/api/v1/ingredients', ['name' => 'Beef', 'unit' => 'g'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Beef')
            ->assertJsonPath('data.unit', 'g');

        $this->getJson('/api/v1/ingredients')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Beef');
    }

    public function test_rejects_empty_ingredient_name(): void
    {
        $this->postJson('/api/v1/ingredients', ['name' => '', 'unit' => 'g'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_rejects_missing_unit(): void
    {
        $this->postJson('/api/v1/ingredients', ['name' => 'Beef'])
            ->assertStatus(422);
    }

    public function test_rejects_case_insensitive_duplicate_ingredient_name(): void
    {
        $this->postJson('/api/v1/ingredients', ['name' => 'Beef', 'unit' => 'g'])->assertCreated();

        $this->postJson('/api/v1/ingredients', ['name' => '  beef  ', 'unit' => 'g'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DUPLICATE_NAME');
    }

    public function test_creates_and_lists_suppliers(): void
    {
        $this->postJson('/api/v1/suppliers', ['name' => 'Acme Foods'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Acme Foods');

        $this->getJson('/api/v1/suppliers')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_rejects_empty_supplier_name(): void
    {
        $this->postJson('/api/v1/suppliers', ['name' => ''])->assertStatus(422);
    }

    public function test_rejects_duplicate_supplier_name(): void
    {
        $this->postJson('/api/v1/suppliers', ['name' => 'Acme Foods'])->assertCreated();
        $this->postJson('/api/v1/suppliers', ['name' => 'ACME FOODS'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DUPLICATE_NAME');
    }
}
