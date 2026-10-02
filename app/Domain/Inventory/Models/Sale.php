<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recorded POS sale event. Immutable through application code; quantity
 * is a bounded positive integer (sale *count*), not a Quantity value object.
 */
class Sale extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['event_id', 'menu_item_id', 'quantity', 'request_hash'];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
