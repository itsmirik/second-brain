<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Atheer\AtheerApiClient;
use App\Support\Reports\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

/**
 * Period reports: business finance over a selectable window (day … year),
 * pulled from the Atheer report API. Other sections join once they have data.
 */
class ReportsController extends Controller
{
    public function __construct(private readonly AtheerApiClient $atheer) {}

    public function index(Request $request): Response
    {
        try {
            $period = Period::make($request->query('period'), $request->query('date'));
        } catch (InvalidArgumentException) {
            $period = Period::make('month', null);
        }

        try {
            $finance = $this->atheer->finance($period->fromDate(), $period->toDate());
            $error = null;
        } catch (Throwable $e) {
            report($e);
            $finance = null;
            $error = 'Atheer ERP is temporarily unavailable.';
        }

        return Inertia::render('Reports', [
            'period' => [
                ...$period->toArray(),
                'canGoNext' => ! $period->isCurrentOrLatest(),
            ],
            'types' => Period::TYPES,
            'finance' => $finance,
            'error' => $error,
        ]);
    }
}
