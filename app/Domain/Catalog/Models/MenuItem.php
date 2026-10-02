<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Inventory\Models\Sale;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
