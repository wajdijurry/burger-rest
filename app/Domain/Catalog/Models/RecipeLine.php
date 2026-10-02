<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Shared\Casts\AsQuantity;
use App\Domain\Shared\Quantity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ingredient + quantity within a menu item's recipe. Create-only for
 * this scope: recipes are never edited, so historical sale deductions are
 * never recomputed from a "current" recipe (RecordSale snapshots the
 * deduction amount into stock_movements at sale time).
 *
 * @property Quantity $quantity
 */
class RecipeLine extends Model
{
    use HasFactory;

    protected $fillable = ['menu_item_id', 'ingredient_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => AsQuantity::class];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
