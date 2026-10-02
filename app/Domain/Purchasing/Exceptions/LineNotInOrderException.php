<?php

namespace App\Domain\Purchasing\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Raised when a delivery line references a purchase_order_line_id that
 * exists but does not belong to the target purchase order (or does not
 * exist at all). Always a client input problem -> 422, not 404, since the
 * delivery request as a whole is malformed.
 */
class LineNotInOrderException extends DomainException
{
    public function __construct(public readonly int $purchaseOrderLineId, public readonly int $purchaseOrderId)
    {
        parent::__construct(
            "Purchase order line {$purchaseOrderLineId} does not belong to order {$purchaseOrderId}."
        );
    }

    public function errorCode(): string
    {
        return 'LINE_NOT_IN_ORDER';
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function fieldErrors(): array
    {
        return ['lines' => [$this->getMessage()]];
    }
}
