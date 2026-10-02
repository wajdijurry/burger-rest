<?php

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\Quantity;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Eloquent cast binding a NUMERIC(18,3) column to the Quantity value object
 * in both directions, so no business code ever sees a raw string/float for
 * these columns.
 *
 * @implements CastsAttributes<Quantity, Quantity|string>
 */
class AsQuantity implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?Quantity
    {
        if ($value === null) {
            return null;
        }

        // The pgsql PDO driver returns NUMERIC columns as plain strings, so
        // this never passes through a float.
        return Quantity::fromString((string) $value);
    }

    public function set($model, string $key, $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $quantity = $value instanceof Quantity ? $value : Quantity::fromString((string) $value);

        return [$key => $quantity->toString()];
    }
}
