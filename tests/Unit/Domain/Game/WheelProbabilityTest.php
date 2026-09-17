<?php

namespace Tests\Unit\Domain\Game;

use App\Domain\Game\Services\WeightedRandomizer;
use App\Support\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;

/**
 * تست توزیع احتمال — تست تخصصی اجباری CI (فصل ۶-۳ و ۱۰-۲):
 * موتور وزنی ده‌ها هزار بار اجرا و توزیع با تلورانس ±۲ واحد درصد مقایسه می‌شود.
 */
final class WheelProbabilityTest extends TestCase
{
    private const ITERATIONS = 10000;

    public function test_weighted_distribution_within_tolerance(): void
    {
        $segments = [
            ['label' => 'A', 'weight' => 40], // 40%
            ['label' => 'B', 'weight' => 30], // 30%
            ['label' => 'C', 'weight' => 20], // 20%
            ['label' => 'D', 'weight' => 10], // 10%
        ];

        $counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $picked = WeightedRandomizer::pick($segments);
            $counts[$segments[$picked]['label']]++;
        }

        foreach ($segments as $segment) {
            $expected = $segment['weight']; // درصد
            $actual = $counts[$segment['label']] / self::ITERATIONS * 100;

            $this->assertEqualsWithDelta(
                $expected,
                $actual,
                2.0, // تلورانس ±۲ واحد درصد (فصل ۶-۳)
                "Segment {$segment['label']}: expected ~{$expected}%, got ".round($actual, 2).'%',
            );
        }
    }

    public function test_zero_total_weight_throws(): void
    {
        $this->expectException(ApiException::class);

        WeightedRandomizer::pick([
            ['label' => 'A', 'weight' => 0],
        ]);
    }

    public function test_single_segment_always_wins(): void
    {
        $segments = [['label' => 'Only', 'weight' => 5]];

        for ($i = 0; $i < 50; $i++) {
            $picked = WeightedRandomizer::pick($segments);
            $this->assertSame(0, $picked);
        }
    }
}
