<?php

declare(strict_types=1);

namespace App\Support\Charity;

use App\Models\Setting;
use App\Support\Money\MoneyReporter;
use Carbon\CarbonImmutable;

/**
 * The charity (sadaqa) obligation engine.
 *
 * Each month has a profit (auto-summed across Atheer + money sections by the
 * MoneyReporter, or a manual override the owner types in). The obligation is
 * that profit times the owner's charity percentage — computed on profit BEFORE
 * any giving. Actual giving is logged as free-text entries in the "charity"
 * section; the "remaining" is obligation minus what was given that month.
 *
 * @phpstan-type MonthRow array{month:string,label:string,profit:float,auto_profit:float,is_override:bool,percentage:float,obligation:float,given:float,remaining:float}
 */
final class CharityService
{
    private const PERCENTAGE_KEY = 'charity.percentage';

    private const PROFIT_KEY_PREFIX = 'charity.profit.';

    public function __construct(private readonly MoneyReporter $money) {}

    public function percentage(int $userId): float
    {
        return (float) (Setting::get($userId, self::PERCENTAGE_KEY) ?? '0');
    }

    public function setPercentage(int $userId, float $percentage): void
    {
        $percentage = max(0.0, min(100.0, $percentage));

        Setting::put($userId, self::PERCENTAGE_KEY, (string) $percentage);
    }

    public function profitOverride(int $userId, string $month): ?float
    {
        $value = Setting::get($userId, self::PROFIT_KEY_PREFIX.$month);

        return $value === null ? null : (float) $value;
    }

    public function setProfitOverride(int $userId, string $month, ?float $profit): void
    {
        Setting::put($userId, self::PROFIT_KEY_PREFIX.$month, $profit === null ? null : (string) $profit);
    }

    /**
     * Rows for the most recent $count months, newest first.
     *
     * @return list<MonthRow>
     */
    public function months(int $userId, int $count = 12): array
    {
        $percentage = $this->percentage($userId);
        $rows = [];
        $cursor = CarbonImmutable::now()->startOfMonth();

        for ($i = 0; $i < $count; $i++) {
            $rows[] = $this->row($userId, $cursor->subMonths($i), $percentage);
        }

        return $rows;
    }

    /**
     * @return MonthRow
     */
    public function row(int $userId, CarbonImmutable $anchor, ?float $percentage = null): array
    {
        $percentage ??= $this->percentage($userId);
        $month = $anchor->format('Y-m');
        $from = $anchor->startOfMonth()->toDateString();
        $to = $anchor->endOfMonth()->toDateString();

        $autoProfit = $this->money->profit($userId, $from, $to);
        $override = $this->profitOverride($userId, $month);
        $profit = $override ?? $autoProfit;

        $obligation = round($profit * $percentage / 100, 2);
        $given = $this->money->charityGiven($userId, $from, $to);

        return [
            'month' => $month,
            'label' => $anchor->translatedFormat('F Y'),
            'profit' => round($profit, 2),
            'auto_profit' => round($autoProfit, 2),
            'is_override' => $override !== null,
            'percentage' => $percentage,
            'obligation' => $obligation,
            'given' => round($given, 2),
            'remaining' => round($obligation - $given, 2),
        ];
    }
}
