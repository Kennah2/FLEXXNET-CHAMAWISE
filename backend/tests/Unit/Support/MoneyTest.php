<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parses_whole_amounts(): void
    {
        $this->assertSame(10000, Money::parseToCents('100'));
    }

    public function test_parses_amounts_with_two_decimals(): void
    {
        $this->assertSame(10050, Money::parseToCents('100.50'));
    }

    public function test_parses_amounts_with_one_decimal(): void
    {
        $this->assertSame(10050, Money::parseToCents('100.5'));
    }

    public function test_parses_amounts_with_thousands_separators(): void
    {
        $this->assertSame(125000, Money::parseToCents('1,250.00'));
    }

    public function test_parses_zero(): void
    {
        $this->assertSame(0, Money::parseToCents('0'));
    }

    public function test_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parseToCents('-100');
    }

    public function test_rejects_non_numeric_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parseToCents('abc');
    }

    public function test_rejects_more_than_two_decimal_places(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parseToCents('100.123');
    }

    public function test_formats_cents_as_currency(): void
    {
        $this->assertSame('KES 100.50', Money::format(10050));
    }

    public function test_formats_zero(): void
    {
        $this->assertSame('KES 0.00', Money::format(0));
    }

    public function test_formats_negative_cents(): void
    {
        $this->assertSame('-KES 50.00', Money::format(-5000));
    }

    public function test_formats_with_a_different_currency(): void
    {
        $this->assertSame('USD 10.00', Money::format(1000, 'USD'));
    }
}
