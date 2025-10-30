<?php

namespace Tests\Unit\Support;

use App\Support\Analytics\TrendlineCalculator;
use PHPUnit\Framework\TestCase;

class TrendlineCalculatorTest extends TestCase
{
    public function test_trendline_calculates_positive_slope(): void
    {
        $points = [
            ['2025-10-20', 0.001],
            ['2025-10-21', 0.0015],
            ['2025-10-22', 0.002],
        ];

        $trend = TrendlineCalculator::fromPoints($points);

        $this->assertSame('up', $trend['direction']);
        $this->assertGreaterThan(0, $trend['slope_per_day']);
        $this->assertCount(2, $trend['points']);
        $this->assertEquals(3, $trend['count']);
    }

    public function test_trendline_defaults_with_single_point(): void
    {
        $points = [['2025-10-20', 0.001]];

        $trend = TrendlineCalculator::fromPoints($points);

        $this->assertSame('flat', $trend['direction']);
        $this->assertSame(0.0, $trend['slope_per_day']);
        $this->assertEquals(1, $trend['count']);
    }
}
