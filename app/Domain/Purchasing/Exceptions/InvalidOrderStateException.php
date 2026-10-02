<?php

namespace App\Domain\Purchasing\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Raised for any requested transition/action that the current purchase
 * order status does not allow (e.g. receiving against a draft/closed order,
 * sending a received/closed order). See PurchaseOrderStatus for the single
 * source of truth on which actions each status allows.
 */
class InvalidOrderStateException extends DomainException
{
    public function __construct(string $action, string $currentStatus)
    {
        parent::__construct("Cannot {$action} a purchase order in \"{$currentStatus}\" status.");
    }

    public function errorCode(): string
    {
        return 'INVALID_ORDER_STATE';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
