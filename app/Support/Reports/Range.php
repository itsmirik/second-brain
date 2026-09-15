<?php

declare(strict_types=1);

namespace App\Support\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * A resolved [from, to] date window, built either from a relative keyword
 * ("last_month") or from explicit dates.
 *
 * Period handles the dashboard's pageable windows (prev/next, Russian labels).
 * This is the conversational counterpart: the agent says "last month" and gets
 * concrete dates without doing calendar maths itself — which models get wrong.
 */
final class Range
{
    /** Relative windows the agent may ask for. */
    public const KEYWORDS = [
        'today',
        'yesterday',
        'this_week',
        'last_week',
        'this_month',
        'last_month',
        'last_7_days',
        'last_30_days',
        'this_quarter',
        'last_quarter',
        'this_year',
        'last_year',
        'all',
    ];

    private const DEFAULT_KEYWORD = 'this_month';

    /** The earliest date worth scanning — nothing predates the app. */
    private const EPOCH = '1970-01-01';

    private function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $keyword,
    ) {}

    public static function resolve(?string $keyword = null, ?string $from = null, ?string $to = null): self
    {
        $start = self::parse($from);
        $end = self::parse($to);

        if ($start !== null || $end !== null) {
            $today = self::today();

            return new self(
                from: ($start ?? CarbonImmutable::parse(self::EPOCH))->toDateString(),
                to: ($end ?? $today)->toDateString(),
                keyword: 'custom',
            );
        }

        return self::fromKeyword($keyword);
    }

    public static function fromKeyword(?string $keyword): self
    {
        $keyword = in_array($keyword, self::KEYWORDS, true) ? $keyword : self::DEFAULT_KEYWORD;
        $today = self::today();

        [$start, $end] = match ($keyword) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek()],
            'last_week' => [$today->subWeek()->startOfWeek(), $today->subWeek()->endOfWeek()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_7_days' => [$today->subDays(6), $today],
            'last_30_days' => [$today->subDays(29), $today],
            'this_quarter' => [$today->startOfQuarter(), $today->endOfQuarter()],
            'last_quarter' => [$today->subQuarterNoOverflow()->startOfQuarter(), $today->subQuarterNoOverflow()->endOfQuarter()],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'last_year' => [$today->subYearNoOverflow()->startOfYear(), $today->subYearNoOverflow()->endOfYear()],
            'all' => [CarbonImmutable::parse(self::EPOCH), $today],
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };

        return new self($start->toDateString(), $end->toDateString(), $keyword);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'keyword' => $this->keyword,
        ];
    }

    private static function today(): CarbonImmutable
    {
        /** @var CarbonImmutable $today */
        $today = Date::now()->startOfDay();

        return $today;
    }

    private static function parse(?string $date): ?CarbonImmutable
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        try {
            /** @var CarbonImmutable $parsed */
            $parsed = Date::parse($date)->startOfDay();

            return $parsed;
        } catch (Throwable) {
            return null;
        }
    }
}
