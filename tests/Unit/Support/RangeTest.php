<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Reports\Range;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class RangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    }

    public function test_it_resolves_last_month_to_the_previous_calendar_month(): void
    {
        // Arrange + Act
        $range = Range::resolve('last_month');

        // Assert
        $this->assertSame('2026-08-01', $range->from);
        $this->assertSame('2026-08-31', $range->to);
        $this->assertSame('last_month', $range->keyword);
    }

    public function test_it_resolves_today_and_yesterday_to_single_days(): void
    {
        $this->assertSame(['2026-09-15', '2026-09-15'], [Range::resolve('today')->from, Range::resolve('today')->to]);
        $this->assertSame(['2026-09-14', '2026-09-14'], [Range::resolve('yesterday')->from, Range::resolve('yesterday')->to]);
    }

    public function test_explicit_dates_win_over_the_keyword(): void
    {
        // Act
        $range = Range::resolve('last_month', '2026-01-10', '2026-02-20');

        // Assert
        $this->assertSame('2026-01-10', $range->from);
        $this->assertSame('2026-02-20', $range->to);
        $this->assertSame('custom', $range->keyword);
    }

    public function test_a_missing_end_date_runs_to_today(): void
    {
        $range = Range::resolve(null, '2026-09-01');

        $this->assertSame('2026-09-01', $range->from);
        $this->assertSame('2026-09-15', $range->to);
    }

    public function test_it_falls_back_to_this_month_for_an_unknown_keyword(): void
    {
        $range = Range::resolve('sometime');

        $this->assertSame('2026-09-01', $range->from);
        $this->assertSame('2026-09-30', $range->to);
        $this->assertSame('this_month', $range->keyword);
    }

    public function test_all_covers_every_stored_entry(): void
    {
        $range = Range::resolve('all');

        $this->assertSame('1970-01-01', $range->from);
        $this->assertSame('2026-09-15', $range->to);
    }
}
