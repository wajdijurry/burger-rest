<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Catalog\Models\Supplier;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property PurchaseOrderStatus $status
 */
class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = ['supplier_id', 'status', 'sent_at', 'closed_at'];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
