<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sections;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Support\Charity\CharityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Charity (sadaqa) page. Unlike the generic entry sections it carries a
 * monthly obligation calculation (see CharityService): profit × percentage,
 * with the giving logged as free-text entries in the "charity" section.
 */
class CharityController extends Controller
{
    public function __construct(private readonly CharityService $charity) {}

    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        $selected = $this->selectedMonth($request->query('month'));
        $months = $this->charity->months($userId, 12);
        $current = $this->charity->row($userId, $selected);

        $giving = Entry::query()
            ->where('user_id', $userId)
            ->forSection('charity')
            ->whereBetween('occurred_at', [
                $selected->startOfMonth()->toDateString(),
                $selected->endOfMonth()->toDateString(),
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('sections/Charity', [
            'percentage' => $this->charity->percentage($userId),
            'months' => $months,
            'selected' => $current,
            'giving' => $giving->map($this->present(...))->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        Entry::query()->create([
            'user_id' => $userId,
            'section' => 'charity',
            'body' => $validated['body'],
            'amount' => $validated['amount'],
            'occurred_at' => $validated['occurred_at'] ?? Date::today()->toDateString(),
            'tags' => null,
        ]);

        return back();
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        $validated = $request->validate([
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->charity->setPercentage($userId, (float) $validated['percentage']);

        return back();
    }

    public function updateProfit(Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        $validated = $request->validate([
            'month' => ['required', 'string', 'date_format:Y-m'],
            'profit' => ['nullable', 'numeric'],
        ]);

        $profit = $validated['profit'] ?? null;

        $this->charity->setProfitOverride(
            $userId,
            $validated['month'],
            $profit === null ? null : (float) $profit,
        );

        return back();
    }

    private function selectedMonth(mixed $month): CarbonImmutable
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @return array{id:int,body:string,amount:float,occurred_at:string}
     */
    private function present(Entry $entry): array
    {
        return [
            'id' => $entry->id,
            'body' => $entry->body,
            'amount' => (float) $entry->amount,
            'occurred_at' => $entry->occurred_at->toDateString(),
        ];
    }
}
