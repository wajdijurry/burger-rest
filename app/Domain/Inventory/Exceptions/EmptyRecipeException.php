<?php

namespace App\Domain\Inventory\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * A sale was requested for a menu item with no recipe lines. Rejected before
 * any inventory effect is created (brief: "Validate the menu item and its
 * non-empty recipe before creating any inventory effects.").
 */
class EmptyRecipeException extends DomainException
{
    public function __construct(public readonly int $menuItemId)
    {
        parent::__construct("Menu item {$menuItemId} has no recipe lines and cannot be sold.");
    }

    public function errorCode(): string
    {
        return 'EMPTY_RECIPE';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
