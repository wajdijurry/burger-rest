<?php

namespace App\Domain\Catalog\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Duplicate-name policy for ingredients/suppliers/menu items: identity is
 * the database ID, but an exact case-insensitive duplicate name is rejected
 * as a data-entry error rather than silently creating a confusing second
 * catalog entry (documented assumption in README).
 */
class DuplicateNameException extends DomainException
{
    public function __construct(private readonly string $field, string $name)
    {
        parent::__construct("The name \"{$name}\" is already in use.");
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_NAME';
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function fieldErrors(): array
    {
        return [$this->field => [$this->getMessage()]];
    }
}
