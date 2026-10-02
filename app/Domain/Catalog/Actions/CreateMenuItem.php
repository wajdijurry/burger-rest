<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Exceptions\DuplicateNameException;
use App\Domain\Catalog\Models\Ingredient;
use App\Domain\Catalog\Models\MenuItem;
use App\Domain\Shared\Exceptions\InvalidQuantityException;
use App\Domain\Shared\Quantity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateMenuItem
{
    /**
     * @param  array<int, array{ingredient_id: int, quantity: string}>  $lines
     */
    public function execute(string $name, array $lines): MenuItem
    {
        $name = trim($name);

        if ($lines === []) {
            throw new InvalidQuantityException('A recipe must have at least one ingredient line.', 'lines');
        }

        $ingredientIds = array_column($lines, 'ingredient_id');
        if (count($ingredientIds) !== count(array_unique($ingredientIds))) {
            throw new InvalidQuantityException('Each ingredient may appear only once in a recipe.', 'lines');
        }

        $existingCount = Ingredient::whereIn('id', $ingredientIds)->count();
        if ($existingCount !== count($ingredientIds)) {
            throw new InvalidQuantityException('One or more ingredient IDs do not exist.', 'lines');
        }

        // Parse + validate every quantity before writing anything.
        $parsedLines = array_map(
            fn (array $line) => [
                'ingredient_id' => $line['ingredient_id'],
                'quantity' => Quantity::fromString((string) $line['quantity'])
                    ->assertPositive('lines')
                    ->assertWithinBound('lines'),
            ],
            $lines,
        );

        return DB::transaction(function () use ($name, $parsedLines) {
            if (MenuItem::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                throw new DuplicateNameException('name', $name);
            }

            try {
                $menuItem = MenuItem::create(['name' => $name]);
            } catch (QueryException $e) {
                if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'menu_items_name_lower_unique')) {
                    throw new DuplicateNameException('name', $name);
                }

                throw $e;
            }

            foreach ($parsedLines as $line) {
                $menuItem->recipeLines()->create([
                    'ingredient_id' => $line['ingredient_id'],
                    'quantity' => $line['quantity'],
                ]);
            }

            return $menuItem->load('recipeLines.ingredient');
        });
    }
}
