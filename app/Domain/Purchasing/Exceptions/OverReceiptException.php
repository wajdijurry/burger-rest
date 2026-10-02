<?php

namespace App\Domain\Purchasing\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Raised when any line of a delivery requests more than its current
 * outstanding quantity. Rejects the whole delivery - never clamps or
 * partially applies it.
 */
class OverReceiptException extends DomainException
{
    public function __construct(
        public readonly int $purchaseOrderLineId,
        public readonly string $requested,
        public readonly string $outstanding,
    ) {
        parent::__construct(
            "Line {$purchaseOrderLineId}: requested {$requested} exceeds outstanding {$outstanding}."
        );
    }

    public function errorCode(): string
    {
        return 'OVER_RECEIPT';
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function fieldErrors(): array
    {
        return ['lines' => [$this->getMessage()]];
    }
}
