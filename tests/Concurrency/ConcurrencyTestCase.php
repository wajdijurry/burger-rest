<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;
use Tests\Support\ConcurrentServer;
use Tests\TestCase;

/**
 * Deliberately does *not* use RefreshDatabase: that trait wraps each test in
 * an uncommitted transaction, which would be invisible to the separate
 * server process/connection these tests spawn (brief section 9: "Avoid
 * wrapping setup in an outer test transaction invisible to worker
 * connections"). Fixtures created here via the real Actions commit for
 * real, and are torn down by a plain committed TRUNCATE before each test.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    use ConcurrentServer;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement(
            'TRUNCATE TABLE stock_movements, sales, delivery_lines, deliveries, '.
            'purchase_order_lines, purchase_orders, recipe_lines, menu_items, '.
            'ingredients, suppliers RESTART IDENTITY CASCADE'
        );
    }

    protected function tearDown(): void
    {
        $this->stopConcurrentServer();
        parent::tearDown();
    }
}
