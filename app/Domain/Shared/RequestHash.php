<?php

namespace App\Domain\Shared;

/**
 * Canonicalizes a request payload before hashing it for idempotency
 * comparison, so that semantically-equivalent payloads compare equal:
 *  - decimal strings are normalized to Quantity's canonical form
 *    ("1" and "1.000" are the same quantity);
 *  - delivery lines are sorted by their stable purchase_order_line_id, so
 *    reordering the same lines is not treated as a different operation.
 *
 * Sale identity intentionally only ever includes menu_item_id + quantity
 * (never today's recipe contents - see RecordSale).
 */
final class RequestHash
{
    /**
     * @param  array<int, array{purchase_order_line_id: int, quantity: string}>  $lines
     */
    public static function forDelivery(array $lines): string
    {
        $canonical = array_map(
            fn (array $line) => [
                'purchase_order_line_id' => (int) $line['purchase_order_line_id'],
                'quantity' => Quantity::fromString((string) $line['quantity'])->toString(),
            ],
            $lines,
        );

        usort($canonical, fn ($a, $b) => $a['purchase_order_line_id'] <=> $b['purchase_order_line_id']);

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
    }

    public static function forSale(int $menuItemId, int $quantity): string
    {
        return hash('sha256', json_encode(
            ['menu_item_id' => $menuItemId, 'quantity' => $quantity],
            JSON_THROW_ON_ERROR,
        ));
    }
}
