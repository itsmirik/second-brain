<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Count plus income / expense / net over a set of entries, summed by the
 * database. Never total a page of loaded rows instead — that silently drops
 * everything past the page.
 *
 * Sign convention as everywhere: income positive, expense negative. "expense"
 * here is the absolute value of what went out; net = income - expense.
 *
 * @phpstan-type Totals array{entries:int,income:float,expense:float,net:float}
 * @phpstan-type Group array{key:int|string|null,totals:Totals}
 */
final class EntryTotals
{
    /**
     * @param  Builder<Entry>  $query
     * @return Totals
     */
    public static function of(Builder $query): array
    {
        $row = self::aggregate($query)->first();

        return self::totals($row === null ? [] : (array) $row);
    }

    /**
     * One group per distinct value of $column (e.g. section, house_id), in
     * that column's order. Entries with a null value form their own group.
     *
     * @param  Builder<Entry>  $query
     * @return list<Group>
     */
    public static function groupedBy(Builder $query, string $column): array
    {
        $rows = self::aggregate($query)
            ->addSelect($column.' as group_key')
            ->groupBy($column)
            ->orderBy($column)
            ->get();

        $groups = [];

        foreach ($rows as $row) {
            /** @var array<string, mixed> $data */
            $data = (array) $row;
            $key = $data['group_key'] ?? null;

            $groups[] = [
                'key' => is_int($key) || is_string($key) ? $key : null,
                'totals' => self::totals($data),
            ];
        }

        return $groups;
    }

    /**
     * Totals of an empty set.
     *
     * @return Totals
     */
    public static function zero(): array
    {
        return self::totals([]);
    }

    /**
     * @param  Builder<Entry>  $query
     */
    private static function aggregate(Builder $query): QueryBuilder
    {
        return (clone $query)
            ->toBase()
            ->selectRaw('count(*) as entries')
            ->selectRaw('coalesce(sum(case when amount > 0 then amount else 0 end), 0) as income')
            ->selectRaw('coalesce(sum(case when amount < 0 then -amount else 0 end), 0) as expense');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return Totals
     */
    private static function totals(array $row): array
    {
        $income = round((float) ($row['income'] ?? 0), 2);
        $expense = round((float) ($row['expense'] ?? 0), 2);

        return [
            'entries' => (int) ($row['entries'] ?? 0),
            'income' => $income,
            'expense' => $expense,
            'net' => round($income - $expense, 2),
        ];
    }
}
