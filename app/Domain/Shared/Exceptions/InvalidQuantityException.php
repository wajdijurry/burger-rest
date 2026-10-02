<?php

namespace App\Domain\Shared\Exceptions;

/**
 * Raised by Quantity parsing/validation. Always a client input problem
 * (malformed decimal, wrong precision, non-positive where positive is
 * required, or over the per-line magnitude bound) -> maps to HTTP 422.
 */
class InvalidQuantityException extends DomainException
{
    public function __construct(string $message, private readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'INVALID_QUANTITY';
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function fieldErrors(): array
    {
        return $this->field ? [$this->field => [$this->getMessage()]] : [];
    }
}
