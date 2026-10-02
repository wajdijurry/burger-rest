<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Shared\Casts\AsQuantity;
use App\Domain\Shared\Quantity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property Quantity $quantity_received
 */
class DeliveryLine extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['delivery_id', 'purchase_order_line_id', 'quantity_received'];

    protected function casts(): array
    {
        return ['quantity_received' => AsQuantity::class];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function stockMovement(): HasOne
    {
        return $this->hasOne(StockMovement::class);
    }
}
