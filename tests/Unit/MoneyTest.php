<?php

namespace Tests\Unit;

use App\Exceptions\BusinessRuleException;
use App\Services\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_arithmetic_is_exact(): void
    {
        $this->assertSame(30, Money::minor('0.10') + Money::minor('0.20'));
        $this->assertSame('0.30', Money::decimal(30));
        $this->assertSame(100001, Money::addLine(0, '1000.01', 1));
    }

    public function test_fractional_minor_units_are_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        Money::minor('1000.001');
    }
}
