<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Ingredient;
use App\Domain\Purchasing\Models\DeliveryLine;
use App\Domain\Shared\Casts\AsQuantity;
use App\Domain\Shared\Quantity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable, append-only ledger entry. Current stock for an ingredient is
 * always sum(quantity) over its movements - never a mutable balance column.
 *
 * Exactly one of delivery_line_id/sale_id is set (enforced by a DB check
 * constraint, not just here) and identifies the unambiguous source of this
 * movement's effect.
 *
 * @property Quantity $quantity
 */
class StockMovement extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_SALE = 'sale';

    protected $fillable = ['ingredient_id', 'quantity', 'type', 'delivery_line_id', 'sale_id'];

    protected function casts(): array
    {
        return ['quantity' => AsQuantity::class];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function deliveryLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryLine::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
