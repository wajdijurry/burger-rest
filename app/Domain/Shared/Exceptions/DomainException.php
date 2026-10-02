<?php

namespace App\Domain\Shared\Exceptions;

/**
 * Base type for business-rule violations that the API exception handler
 * (bootstrap/app.php) renders as a consistent JSON error envelope:
 * { "error": { "code", "message", "fields"? } }.
 *
 * Keeping one small hierarchy here (rather than a class per rule) is enough
 * for this project's scope; each concrete exception just fixes its HTTP
 * status and machine-readable code.
 */
abstract class DomainException extends \RuntimeException
{
    abstract public function errorCode(): string;

    abstract public function httpStatus(): int;

    /** @return array<string, array<int, string>> */
    public function fieldErrors(): array
    {
        return [];
    }
}
