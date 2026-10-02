<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Exceptions\InvalidOrderStateException;
use App\Domain\Purchasing\Exceptions\LineNotInOrderException;
use App\Domain\Purchasing\Exceptions\OverReceiptException;
use App\Domain\Purchasing\Models\Delivery;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use App\Domain\Shared\Exceptions\IdempotencyConflictException;
use App\Domain\Shared\Exceptions\InvalidQuantityException;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\RequestHash;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Records a (possibly partial) delivery against a purchase order.
 *
 * Concurrency (brief section 6): a single lockForUpdate() on the parent
 * purchase_orders row, taken before anything else in the transaction, is
 * the whole concurrency story here. Because every outstanding-quantity
 * check and every write for this order happens only after that lock is
 * held, two concurrent deliveries against the same order are fully
 * serialized by Postgres: the first to acquire the lock commits its
 * accepted receipt, and the second re-reads outstanding quantities *after*
 * that commit and is correctly rejected (or correctly partially accepted)
 * against the up-to-date numbers. No per-line locks are needed because all
 * lines of one order are only ever touched while holding that one lock.
 */
class ReceiveDelivery
{
    /**
     * @param  array<int, array{purchase_order_line_id: int, quantity: string}>  $lines
     */
    public function execute(int $purchaseOrderId, string $idempotencyKey, array $lines): DeliveryResult
    {
        $parsedLines = $this->parseAndValidateShape($lines);
        $requestHash = RequestHash::forDelivery($this->toHashable($parsedLines));

        try {
            return DB::transaction(
                fn () => $this->attempt($purchaseOrderId, $idempotencyKey, $requestHash, $parsedLines)
            );
        } catch (QueryException $e) {
            if (! $this->isIdempotencyKeyViolation($e)) {
                throw $e;
            }

            // Defensive backstop: the parent-lock discipline above should
            // make this race impossible, but if it is ever hit, the current
            // transaction was already rolled back by DB::transaction()
            // before rethrowing, so this is a fresh, usable read.
            $existing = Delivery::where('purchase_order_id', $purchaseOrderId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $existing) {
                throw $e;
            }

            return $this->resolveReplay($existing, $requestHash);
        }
    }

    private function attempt(
        int $purchaseOrderId,
        string $idempotencyKey,
        string $requestHash,
        array $parsedLines,
    ): DeliveryResult {
        /** @var PurchaseOrder $order */
        $order = PurchaseOrder::whereKey($purchaseOrderId)->lockForUpdate()->firstOrFail();

        // Check for a replay BEFORE applying any state/outstanding rule, so
        // that replaying the delivery that closed this order still works
        // even though the order is now closed.
        $existing = Delivery::where('purchase_order_id', $order->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $this->resolveReplay($existing, $requestHash);
        }

        if (! $order->status->canAcceptDelivery()) {
            throw new InvalidOrderStateException('receive a delivery for', $order->status->value);
        }

        // Re-read, under the lock, which lines belong to this order.
        $orderLines = PurchaseOrderLine::where('purchase_order_id', $order->id)->get()->keyBy('id');

        foreach ($parsedLines as $line) {
            if (! $orderLines->has($line['purchase_order_line_id'])) {
                throw new LineNotInOrderException($line['purchase_order_line_id'], $order->id);
            }
        }

        // Cumulative received per line, re-read under the lock.
        $alreadyReceived = DB::table('delivery_lines')
            ->whereIn('purchase_order_line_id', $orderLines->keys())
            ->selectRaw('purchase_order_line_id, COALESCE(SUM(quantity_received), 0) as total')
            ->groupBy('purchase_order_line_id')
            ->pluck('total', 'purchase_order_line_id');

        $outstandingByLine = $orderLines->map(
            fn (PurchaseOrderLine $orderLine) => $orderLine->quantity_ordered->subtract(
                Quantity::fromString((string) ($alreadyReceived[$orderLine->id] ?? '0'))
            )
        );

        // Validate the whole delivery before writing anything: over-receiving
        // on any single line rejects the entire delivery with no partial
        // effect.
        foreach ($parsedLines as $line) {
            $outstanding = $outstandingByLine[$line['purchase_order_line_id']];

            if ($line['quantity']->isGreaterThan($outstanding)) {
                throw new OverReceiptException(
                    $line['purchase_order_line_id'],
                    $line['quantity']->toString(),
                    $outstanding->toString(),
                );
            }
        }

        $delivery = Delivery::create([
            'purchase_order_id' => $order->id,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
        ]);

        foreach ($parsedLines as $line) {
            /** @var PurchaseOrderLine $orderLine */
            $orderLine = $orderLines[$line['purchase_order_line_id']];

            $deliveryLine = $delivery->lines()->create([
                'purchase_order_line_id' => $orderLine->id,
                'quantity_received' => $line['quantity'],
            ]);

            // Ingredient comes from the purchase-order line, never from the
            // client, so stock always moves for the ingredient actually
            // ordered.
            StockMovement::create([
                'ingredient_id' => $orderLine->ingredient_id,
                'quantity' => $line['quantity'],
                'type' => StockMovement::TYPE_RECEIPT,
                'delivery_line_id' => $deliveryLine->id,
            ]);

            $outstandingByLine[$orderLine->id] = $outstandingByLine[$orderLine->id]->subtract($line['quantity']);
        }

        $everyLineFullyReceived = $outstandingByLine->every(fn (Quantity $q) => $q->isZero());

        $order->update([
            'status' => $order->status->statusAfterReceiving($everyLineFullyReceived),
            'closed_at' => $everyLineFullyReceived ? now() : $order->closed_at,
        ]);

        return new DeliveryResult($delivery->load('lines.purchaseOrderLine.ingredient'), $order->refresh(), replayed: false);
    }

    private function resolveReplay(Delivery $existing, string $requestHash): DeliveryResult
    {
        if (! hash_equals($existing->request_hash, $requestHash)) {
            throw new IdempotencyConflictException('delivery', $existing->idempotency_key);
        }

        return new DeliveryResult(
            $existing->load('lines.purchaseOrderLine.ingredient'),
            $existing->purchaseOrder,
            replayed: true,
        );
    }

    /**
     * @return array<int, array{purchase_order_line_id: int, quantity: Quantity}>
     */
    private function parseAndValidateShape(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidQuantityException('A delivery must have at least one line.', 'lines');
        }

        $seen = [];
        foreach ($lines as $line) {
            $id = (int) ($line['purchase_order_line_id'] ?? 0);
            if (isset($seen[$id])) {
                throw new InvalidQuantityException("Duplicate purchase_order_line_id {$id} in delivery.", 'lines');
            }
            $seen[$id] = true;
        }

        return array_map(
            fn (array $line) => [
                'purchase_order_line_id' => (int) $line['purchase_order_line_id'],
                'quantity' => Quantity::fromString((string) $line['quantity'])
                    ->assertPositive('lines')
                    ->assertWithinBound('lines'),
            ],
            $lines,
        );
    }

    /**
     * @param  array<int, array{purchase_order_line_id: int, quantity: Quantity}>  $parsedLines
     */
    private function toHashable(array $parsedLines): array
    {
        return array_map(
            fn (array $line) => [
                'purchase_order_line_id' => $line['purchase_order_line_id'],
                'quantity' => $line['quantity']->toString(),
            ],
            $parsedLines,
        );
    }

    private function isIdempotencyKeyViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505'
            && str_contains($e->getMessage(), 'deliveries_purchase_order_id_idempotency_key_unique');
    }
}
