<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Money\MoneyReporter;
use App\Support\Reports\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Period reports: the whole money picture over a selectable window (day …
 * year) — Atheer plus the money-bearing sections, netted by the MoneyReporter.
 */
class ReportsController extends Controller
{
    public function __construct(private readonly MoneyReporter $money) {}

    public function index(Request $request): Response
    {
        try {
            $period = Period::make($request->query('period'), $request->query('date'));
        } catch (InvalidArgumentException) {
            $period = Period::make('month', null);
        }

        $userId = (int) $request->user()->getAuthIdentifier();
        $report = $this->money->report($userId, $period->fromDate(), $period->toDate());

        $atheer = collect($report['sources'])->firstWhere('key', 'atheer');
        $error = ($atheer !== null && $atheer['available'] === false)
            ? 'Atheer ERP временно недоступен — его показатели показаны как ноль.'
            : null;

        return Inertia::render('Reports', [
            'period' => [
                ...$period->toArray(),
                'canGoNext' => ! $period->isCurrentOrLatest(),
            ],
            'types' => Period::TYPES,
            'report' => $report,
            'error' => $error,
        ]);
    }
}
