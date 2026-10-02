<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Exceptions\DuplicateNameException;
use App\Domain\Catalog\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateSupplier
{
    public function execute(string $name): Supplier
    {
        $name = trim($name);

        return DB::transaction(function () use ($name) {
            if (Supplier::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                throw new DuplicateNameException('name', $name);
            }

            try {
                return Supplier::create(['name' => $name]);
            } catch (QueryException $e) {
                if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'suppliers_name_lower_unique')) {
                    throw new DuplicateNameException('name', $name);
                }

                throw $e;
            }
        });
    }
}
