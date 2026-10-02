<?php

namespace Tests\Unit;

use App\Domain\Shared\Exceptions\InvalidQuantityException;
use App\Domain\Shared\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuantityTest extends TestCase
{
    public function test_parses_integer_string_and_normalizes_to_three_decimals(): void
    {
        $this->assertSame('150.000', Quantity::fromString('150')->toString());
    }

    public function test_parses_already_canonical_decimal(): void
    {
        $this->assertSame('0.125', Quantity::fromString('0.125')->toString());
    }

    public function test_trims_surrounding_whitespace(): void
    {
        $this->assertSame('4.500', Quantity::fromString('  4.5  ')->toString());
    }

    public function test_one_and_one_point_zero_zero_zero_are_equal(): void
    {
        $this->assertTrue(Quantity::fromString('1')->equals(Quantity::fromString('1.000')));
    }

    public function test_negative_values_parse_and_normalize(): void
    {
        $this->assertSame('-4.500', Quantity::fromString('-4.5')->toString());
    }

    public function test_zero_normalizes_sign(): void
    {
        $this->assertSame('0.000', Quantity::fromString('-0')->toString());
        $this->assertSame('0.000', Quantity::fromString('-0.000')->toString());
    }

    #[DataProvider('invalidInputs')]
    public function test_rejects_invalid_input(string $input): void
    {
        $this->expectException(InvalidQuantityException::class);
        Quantity::fromString($input);
    }

    public static function invalidInputs(): array
    {
        return [
            'empty string' => [''],
            'whitespace only' => ['   '],
            'four decimal places' => ['1.2345'],
            'scientific notation' => ['1e3'],
            'infinity' => ['Infinity'],
            'nan' => ['NaN'],
            'leading zero' => ['01.5'],
            'trailing dot' => ['1.'],
            'letters' => ['abc'],
            'comma decimal' => ['1,5'],
        ];
    }

    public function test_add(): void
    {
        $result = Quantity::fromString('10.500')->add(Quantity::fromString('4.250'));
        $this->assertSame('14.750', $result->toString());
    }

    public function test_subtract_can_go_negative(): void
    {
        $result = Quantity::fromString('4')->subtract(Quantity::fromString('10'));
        $this->assertSame('-6.000', $result->toString());
        $this->assertTrue($result->isNegative());
    }

    public function test_multiply_by_integer(): void
    {
        $result = Quantity::fromString('150')->multiplyByInteger(3);
        $this->assertSame('450.000', $result->toString());
    }

    public function test_multiply_by_fractional_piece_quantity(): void
    {
        $result = Quantity::fromString('0.5')->multiplyByInteger(3);
        $this->assertSame('1.500', $result->toString());
    }

    public function test_multiply_by_non_positive_integer_rejected(): void
    {
        $this->expectException(InvalidQuantityException::class);
        Quantity::fromString('5')->multiplyByInteger(0);
    }

    public function test_negate(): void
    {
        $this->assertSame('-150.000', Quantity::fromString('150')->negate()->toString());
        $this->assertSame('150.000', Quantity::fromString('-150')->negate()->toString());
        $this->assertSame('0.000', Quantity::zero()->negate()->toString());
    }

    public function test_comparisons(): void
    {
        $a = Quantity::fromString('5');
        $b = Quantity::fromString('10');

        $this->assertTrue($b->isGreaterThan($a));
        $this->assertTrue($a->isLessThan($b));
        $this->assertTrue($a->isGreaterThanOrEqual($a));
        $this->assertFalse($a->isGreaterThan($a));
    }

    public function test_is_zero_positive_negative(): void
    {
        $this->assertTrue(Quantity::zero()->isZero());
        $this->assertTrue(Quantity::fromString('1')->isPositive());
        $this->assertTrue(Quantity::fromString('-1')->isNegative());
        $this->assertFalse(Quantity::zero()->isPositive());
        $this->assertFalse(Quantity::zero()->isNegative());
    }

    public function test_assert_positive_rejects_zero_and_negative(): void
    {
        $this->expectException(InvalidQuantityException::class);
        Quantity::zero()->assertPositive();
    }

    public function test_assert_positive_rejects_negative(): void
    {
        $this->expectException(InvalidQuantityException::class);
        Quantity::fromString('-5')->assertPositive();
    }

    public function test_assert_positive_passes_through_positive_value(): void
    {
        $qty = Quantity::fromString('5')->assertPositive();
        $this->assertSame('5.000', $qty->toString());
    }

    public function test_assert_within_bound_accepts_the_maximum(): void
    {
        $qty = Quantity::fromString('1000000')->assertWithinBound();
        $this->assertSame('1000000.000', $qty->toString());
    }

    public function test_assert_within_bound_rejects_above_the_maximum(): void
    {
        $this->expectException(InvalidQuantityException::class);
        Quantity::fromString('1000000.001')->assertWithinBound();
    }

    public function test_json_serializes_as_plain_string(): void
    {
        $qty = Quantity::fromString('12.5');
        $this->assertSame('"12.500"', json_encode($qty));
    }
}
