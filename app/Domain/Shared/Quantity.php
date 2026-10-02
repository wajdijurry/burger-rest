<?php

namespace App\Domain\Shared;

use App\Domain\Shared\Exceptions\InvalidQuantityException;
use Stringable;

/**
 * Immutable exact-decimal quantity, backed by BCMath, matching the
 * PostgreSQL NUMERIC(18,3) columns it is persisted to.
 *
 * This is the only place in the codebase that is allowed to do quantity
 * arithmetic. Business code should never touch bcadd()/bcsub() directly nor
 * cast a quantity to float/int for arithmetic purposes.
 *
 * The canonical string form always has exactly 3 digits after the decimal
 * point (e.g. "150.000", "0.125", "-20.000"). The parser allows an optional
 * leading '-' because stock movements are signed (receipts positive, sales
 * negative); whether a *positive* value is required is a separate, explicit
 * check via assertPositive()/assertNonNegative(), applied by callers that
 * know the business rule (recipe/order/delivery quantities must be positive;
 * a computed sale deduction must not be).
 */
final class Quantity implements \JsonSerializable, Stringable
{
    public const SCALE = 3;

    /** Per-line bound from the brief: a single user-entered quantity may not
     * exceed one million canonical units. This is a business-rule bound
     * applied at input time, not a storage/column limit. */
    public const MAX_MAGNITUDE = '1000000.000';

    /** Canonical form: optional '-', digits, '.', exactly SCALE digits. */
    private const CANONICAL_PATTERN = '/^-?(0|[1-9]\d*)\.\d{3}$/';

    /** Strict input form accepted from clients: optional '-', digits,
     * optionally '.', up to SCALE decimal digits. No leading zeros other
     * than a bare "0", no scientific notation, no whitespace. */
    private const INPUT_PATTERN = '/^-?(0|[1-9]\d*)(\.\d{1,3})?$/';

    private function __construct(private readonly string $value) {}

    public static function zero(): self
    {
        return new self('0.000');
    }

    /**
     * Parse a decimal string such as "150", "150.000", "0.125" or "-4.5".
     * Rejects scientific notation, non-finite values, more than 3 decimal
     * places, leading/trailing whitespace, and anything not matching the
     * strict numeric grammar above.
     */
    public static function fromString(string $raw): self
    {
        $trimmed = trim($raw);

        if ($trimmed === '' || ! preg_match(self::INPUT_PATTERN, $trimmed)) {
            throw new InvalidQuantityException(
                "Quantity \"{$raw}\" is not a valid decimal with at most 3 decimal places."
            );
        }

        return new self(self::normalize($trimmed));
    }

    private static function normalize(string $validInput): string
    {
        $negative = $validInput[0] === '-';
        $unsigned = $negative ? substr($validInput, 1) : $validInput;

        [$whole, $fraction] = str_contains($unsigned, '.')
            ? explode('.', $unsigned, 2)
            : [$unsigned, ''];

        $fraction = str_pad($fraction, self::SCALE, '0');

        // Normalize "-0.000" down to a plain zero: sign is meaningless there.
        if ($whole === '0' && $fraction === str_repeat('0', self::SCALE)) {
            return '0.000';
        }

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    public function add(self $other): self
    {
        return new self(bcadd($this->value, $other->value, self::SCALE));
    }

    public function subtract(self $other): self
    {
        return new self(bcsub($this->value, $other->value, self::SCALE));
    }

    /** Multiply by a positive integer sale/line count (never by a float). */
    public function multiplyByInteger(int $factor): self
    {
        if ($factor <= 0) {
            throw new InvalidQuantityException('Quantity may only be multiplied by a positive integer.');
        }

        return new self(bcmul($this->value, (string) $factor, self::SCALE));
    }

    public function negate(): self
    {
        if ($this->isZero()) {
            return $this;
        }

        return new self(bcmul($this->value, '-1', self::SCALE));
    }

    public function abs(): self
    {
        return $this->isNegative() ? $this->negate() : $this;
    }

    public function compareTo(self $other): int
    {
        return bccomp($this->value, $other->value, self::SCALE);
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isZero(): bool
    {
        return $this->compareTo(self::zero()) === 0;
    }

    public function isPositive(): bool
    {
        return $this->compareTo(self::zero()) > 0;
    }

    public function isNegative(): bool
    {
        return $this->compareTo(self::zero()) < 0;
    }

    /** @throws InvalidQuantityException */
    public function assertPositive(?string $field = null): self
    {
        if (! $this->isPositive()) {
            throw new InvalidQuantityException(
                "Quantity must be greater than zero, got \"{$this->value}\".",
                $field,
            );
        }

        return $this;
    }

    /** @throws InvalidQuantityException */
    public function assertWithinBound(?string $field = null): self
    {
        if ($this->abs()->compareTo(self::fromString(self::MAX_MAGNITUDE)) > 0) {
            throw new InvalidQuantityException(
                "Quantity \"{$this->value}\" exceeds the maximum of ".self::MAX_MAGNITUDE.' per line.',
                $field,
            );
        }

        return $this;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
