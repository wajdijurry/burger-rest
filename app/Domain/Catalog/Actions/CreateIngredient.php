<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Exceptions\DuplicateNameException;
use App\Domain\Catalog\Models\Ingredient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateIngredient
{
    public function execute(string $name, string $unit): Ingredient
    {
        $name = trim($name);
        $unit = trim($unit);

        return DB::transaction(function () use ($name, $unit) {
            if (Ingredient::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                throw new DuplicateNameException('name', $name);
            }

            try {
                return Ingredient::create(['name' => $name, 'unit' => $unit]);
            } catch (QueryException $e) {
                // Defensive backstop against the race between the exists()
                // check above and the insert: the DB unique index on
                // lower(name) is the real guarantee.
                if ($this->isDuplicateNameViolation($e)) {
                    throw new DuplicateNameException('name', $name);
                }

                throw $e;
            }
        });
    }

    private function isDuplicateNameViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505'
            && str_contains($e->getMessage(), 'ingredients_name_lower_unique');
    }
}
