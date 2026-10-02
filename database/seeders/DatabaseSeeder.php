<?php

namespace Database\Seeders;

use App\Domain\Catalog\Actions\CreateIngredient;
use App\Domain\Catalog\Actions\CreateMenuItem;
use App\Domain\Catalog\Actions\CreateSupplier;
use App\Domain\Inventory\Actions\RecordSale;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Domain\Purchasing\Actions\ReceiveDelivery;
use App\Domain\Purchasing\Actions\SendPurchaseOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Deterministic demo data for the burger-restaurant scenario the brief
 * describes - built entirely through the real domain Actions (never raw
 * `DB::table()->insert()`), so every row this produces has gone through the
 * exact same validation and business rules a real API client would.
 *
 * Destructive by nature: run against a FRESH database only
 * (`php artisan migrate:fresh --seed`). Running it again on top of existing
 * data will likely hit "duplicate name" / "already received" style domain
 * errors rather than silently duplicating rows, because the Actions it
 * calls enforce the same invariants as the API.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $createIngredient = app(CreateIngredient::class);
        $createSupplier = app(CreateSupplier::class);
        $createMenuItem = app(CreateMenuItem::class);
        $createPurchaseOrder = app(CreatePurchaseOrder::class);
        $sendPurchaseOrder = app(SendPurchaseOrder::class);
        $receiveDelivery = app(ReceiveDelivery::class);
        $recordSale = app(RecordSale::class);

        // --- Ingredients ----------------------------------------------------
        $beefPatty = $createIngredient->execute('Beef Patty', 'g');
        $bun = $createIngredient->execute('Burger Bun', 'piece');
        $cheese = $createIngredient->execute('Cheese Slice', 'piece');
        $lettuce = $createIngredient->execute('Lettuce', 'g');
        $tomato = $createIngredient->execute('Tomato', 'g');

        // --- Suppliers --------------------------------------------------------
        $meatSupplier = $createSupplier->execute('Acme Meats Co.');
        $produceSupplier = $createSupplier->execute('Fresh Produce Ltd.');

        // --- Menu items (recipes) ---------------------------------------------
        $classicBurger = $createMenuItem->execute('Classic Burger', [
            ['ingredient_id' => $beefPatty->id, 'quantity' => '200'],
            ['ingredient_id' => $bun->id, 'quantity' => '1'],
            ['ingredient_id' => $cheese->id, 'quantity' => '1'],
        ]);

        $deluxeBurger = $createMenuItem->execute('Cheeseburger Deluxe', [
            ['ingredient_id' => $beefPatty->id, 'quantity' => '200'],
            ['ingredient_id' => $bun->id, 'quantity' => '1'],
            ['ingredient_id' => $cheese->id, 'quantity' => '2'],
            ['ingredient_id' => $lettuce->id, 'quantity' => '20'],
            ['ingredient_id' => $tomato->id, 'quantity' => '20'],
        ]);

        // --- PO #1: meat/bun/cheese restock, sent then fully received --------
        // Demonstrates: send -> single full delivery -> order auto-closes.
        $po1 = $createPurchaseOrder->execute($meatSupplier->id, [
            ['ingredient_id' => $beefPatty->id, 'quantity' => '10000'],
            ['ingredient_id' => $bun->id, 'quantity' => '40'],
            ['ingredient_id' => $cheese->id, 'quantity' => '40'],
        ]);
        $po1 = $sendPurchaseOrder->execute($po1->id);
        $receiveDelivery->execute($po1->id, (string) Str::uuid(), [
            ['purchase_order_line_id' => $po1->lines[0]->id, 'quantity' => '10000'],
            ['purchase_order_line_id' => $po1->lines[1]->id, 'quantity' => '40'],
            ['purchase_order_line_id' => $po1->lines[2]->id, 'quantity' => '40'],
        ]);

        // --- PO #2: produce restock, sent then only PARTIALLY received -------
        // Demonstrates: an order that stays open ("Partially received") with
        // real outstanding quantities, visible on the dashboard.
        $po2 = $createPurchaseOrder->execute($produceSupplier->id, [
            ['ingredient_id' => $lettuce->id, 'quantity' => '5000'],
            ['ingredient_id' => $tomato->id, 'quantity' => '5000'],
        ]);
        $po2 = $sendPurchaseOrder->execute($po2->id);
        $receiveDelivery->execute($po2->id, (string) Str::uuid(), [
            ['purchase_order_line_id' => $po2->lines[0]->id, 'quantity' => '2000'],
            ['purchase_order_line_id' => $po2->lines[1]->id, 'quantity' => '1500'],
        ]);

        // --- A handful of POS sales, consuming stock via the real recipes ----
        $recordSale->execute((string) Str::uuid(), $classicBurger->id, 10);
        $recordSale->execute((string) Str::uuid(), $deluxeBurger->id, 5);
    }
}
