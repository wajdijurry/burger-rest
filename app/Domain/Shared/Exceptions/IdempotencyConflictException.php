<?php

namespace App\Domain\Shared\Exceptions;

/**
 * Raised when a request reuses an idempotency identity (sale event_id, or
 * delivery Idempotency-Key scoped to its order) with a payload that is not
 * equivalent to the one that was originally accepted.
 *
 * A *matching* replay is not an error - it short-circuits to the original
 * result instead of reaching this exception.
 */
class IdempotencyConflictException extends DomainException
{
    public function __construct(string $subject, string $identity)
    {
        parent::__construct(
            "This {$subject} identity ({$identity}) was already used with a different payload."
        );
    }

    public function errorCode(): string
    {
        return 'IDEMPOTENCY_CONFLICT';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
