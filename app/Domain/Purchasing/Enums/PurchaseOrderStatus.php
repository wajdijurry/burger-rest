<?php

namespace App\Domain\Purchasing\Enums;

/**
 * Single source of truth for purchase-order status meaning and the actions
 * each status allows. A separate class per state would be overkill at this
 * scope; keeping the (small, fixed) rule set as plain methods on the enum
 * keeps it in one place and easy to read end-to-end.
 *
 * draft    - created, not sent, no receipts allowed.
 * sent     - sent to supplier, no accepted receipt yet.
 * received - at least one delivery accepted, some lines still outstanding.
 * closed   - every line fully received.
 *
 * Note: ReceiveDelivery decides the *resulting* status purely from whether
 * every line's outstanding quantity is now zero (statusAfterReceiving()),
 * not from a rigid draft->sent->received->closed single-step table. That is
 * what allows a full first delivery to jump straight from "sent" to
 * "closed" atomically, as the brief requires.
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Received = 'received';
    case Closed = 'closed';

    /** Closed orders are excluded from "open orders" views; everything else
     * (including draft, labeled "not yet sent" in the UI) is open. */
    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }

    /** Draft can send; an already-sent order is a no-op (not an error).
     * Received/closed cannot be sent. */
    public function canBeSent(): bool
    {
        return $this === self::Draft || $this === self::Sent;
    }

    public function isAlreadySent(): bool
    {
        return $this === self::Sent;
    }

    /** Only sent/received orders may accept a *new* delivery. Draft and
     * closed are rejected - except for a replay of a previously accepted
     * delivery, which ReceiveDelivery checks before this gate is ever
     * consulted. */
    public function canAcceptDelivery(): bool
    {
        return $this === self::Sent || $this === self::Received;
    }

    public function statusAfterReceiving(bool $everyLineFullyReceived): self
    {
        return $everyLineFullyReceived ? self::Closed : self::Received;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft (not yet sent)',
            self::Sent => 'Sent',
            self::Received => 'Partially received',
            self::Closed => 'Closed',
        };
    }
}
