<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Models\Entry;
use App\Services\Atheer\AtheerApiClient;
use Throwable;

/**
 * The one place that answers "how much money moved, across everything, in this
 * window". Nets Atheer (external ERP) together with the money-bearing entry
 * sections (budget, home business) into a single picture: per-source
 * income / expense / net plus a grand net that the Reports page shows and the
 * charity obligation is calculated from.
 *
 * Sign convention for entries: income is positive, expense is negative.
 * Charity giving lives in its own section and is deliberately NOT part of the
 * profit base (charity is computed on profit *before* giving).
 *
 * @phpstan-type SourceReport array{key:string,label:string,income:float,expense:float,net:float,available:bool}
 * @phpstan-type MoneyReport array{sources:list<SourceReport>,income:float,expense:float,net:float,charity_given:float}
 */
final class MoneyReporter
{
    /**
     * Entry sections (besides Atheer) that carry money and feed the profit base.
     *
     * @var array<string, string>
     */
    private const MONEY_SECTIONS = [
        'budget' => 'Бюджет',
        'home-business' => 'Домашний бизнес',
    ];

    public function __construct(private readonly AtheerApiClient $atheer) {}

    /**
     * @return MoneyReport
     */
    public function report(int $userId, string $from, string $to): array
    {
        $sources = [];

        $sources[] = $this->atheerSource($from, $to);

        foreach (self::MONEY_SECTIONS as $key => $label) {
            $sources[] = $this->entrySource($userId, $key, $label, $from, $to);
        }

        $income = 0.0;
        $expense = 0.0;
        $net = 0.0;

        foreach ($sources as $source) {
            $income += $source['income'];
            $expense += $source['expense'];
            $net += $source['net'];
        }

        return [
            'sources' => $sources,
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($net, 2),
            'charity_given' => $this->charityGiven($userId, $from, $to),
        ];
    }

    /**
     * The profit base for a window: the grand net across Atheer + money
     * sections. This is what the charity percentage is applied to.
     */
    public function profit(int $userId, string $from, string $to): float
    {
        return $this->report($userId, $from, $to)['net'];
    }

    public function charityGiven(int $userId, string $from, string $to): float
    {
        return (float) Entry::query()
            ->where('user_id', $userId)
            ->forSection('charity')
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('amount');
    }

    /**
     * @return SourceReport
     */
    private function atheerSource(string $from, string $to): array
    {
        try {
            $finance = $this->atheer->finance($from, $to);

            return [
                'key' => 'atheer',
                'label' => 'Atheer',
                'income' => (float) ($finance['revenue'] ?? 0),
                'expense' => (float) ($finance['refunds'] ?? 0),
                'net' => (float) ($finance['profit'] ?? 0),
                'available' => true,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'key' => 'atheer',
                'label' => 'Atheer',
                'income' => 0.0,
                'expense' => 0.0,
                'net' => 0.0,
                'available' => false,
            ];
        }
    }

    /**
     * @return SourceReport
     */
    private function entrySource(int $userId, string $key, string $label, string $from, string $to): array
    {
        $entries = Entry::query()
            ->where('user_id', $userId)
            ->forSection($key)
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['amount']);

        $income = 0.0;
        $expense = 0.0;

        foreach ($entries as $entry) {
            $amount = (float) $entry->amount;

            if ($amount >= 0) {
                $income += $amount;
            } else {
                $expense += abs($amount);
            }
        }

        return [
            'key' => $key,
            'label' => $label,
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income - $expense, 2),
            'available' => true,
        ];
    }
}
