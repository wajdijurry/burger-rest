<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'unit'];

    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }

    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
