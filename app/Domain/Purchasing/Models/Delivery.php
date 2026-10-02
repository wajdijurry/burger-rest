<?php

namespace App\Domain\Purchasing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable through application code: no update/delete endpoints exist for
 * deliveries. created_at only (no updated_at - nothing is ever updated).
 */
class Delivery extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['purchase_order_id', 'idempotency_key', 'request_hash'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class);
    }
}
