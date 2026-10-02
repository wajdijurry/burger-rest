<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Catalog\Models\Ingredient;
use App\Domain\Shared\Casts\AsQuantity;
use App\Domain\Shared\Quantity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Quantity $quantity_ordered
 */
class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_order_id', 'ingredient_id', 'quantity_ordered'];

    protected function casts(): array
    {
        return ['quantity_ordered' => AsQuantity::class];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function deliveryLines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class);
    }
}
