<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Sale;

final class SaleResult
{
    public function __construct(
        public readonly Sale $sale,
        public readonly bool $replayed,
    ) {}
}
