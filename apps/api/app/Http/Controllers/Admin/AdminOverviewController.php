<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Money;
use App\Billing\PricingSettings;
use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Models\LedgerEntry;
use App\Models\ModelPrice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOverviewController extends Controller
{
    private const RANGES = [7, 30, 90];

    /**
     * The owner's dashboard: daily series for the chosen range, the same range before it for comparison,
     * and breakdowns by tool, model, and outcome. Money is in dollars, rounded for display.
     */
    public function index(Request $request, PricingSettings $settings): JsonResponse
    {
        $range = (int) $request->query('range', 30);
        abort_unless(in_array($range, self::RANGES, true), 422, 'Range must be 7, 30 or 90 days.');

        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($range - 1)->startOfDay();
        $previousStart = $start->subDays($range);
        $previousEnd = $start;

        $current = $this->totals($start, $end);
        $previous = $this->totals($previousStart, $previousEnd);

        $days = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days[$day->toDateString()] = ['revenue' => 0, 'cost' => 0, 'runs' => 0, 'failed' => 0, 'signups' => 0];
        }

        foreach ($this->dailyRuns($start, $end) as $row) {
            $days[$row->day] = [
                'revenue' => (int) $row->revenue,
                'cost' => (int) $row->cost,
                'runs' => (int) $row->runs,
                'failed' => (int) $row->failed,
                'signups' => $days[$row->day]['signups'] ?? 0,
            ];
        }

        foreach (User::query()->withTrashed()->where('created_at', '>=', $start)->selectRaw('date(created_at) as day, count(*) as n')->groupBy('day')->get() as $row) {
            if (isset($days[$row->day])) {
                $days[$row->day]['signups'] = (int) $row->n;
            }
        }

        $series = collect($days)->map(fn (array $day, string $date) => [
            'date' => $date,
            'revenue' => Money::toUsd($day['revenue']),
            'cost' => Money::toUsd($day['cost']),
            'earned' => Money::toUsd($day['revenue'] - $day['cost']),
            'runs' => $day['runs'],
            'failed' => $day['failed'],
            'signups' => $day['signups'],
        ])->values();

        $succeeded = Generation::query()
            ->where('status', Generation::STATUS_SUCCEEDED)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end);

        $byTool = (clone $succeeded)
            ->select('tool', DB::raw('count(*) as runs'), DB::raw('sum(price_micros) as revenue'), DB::raw('sum(cost_micros) as cost'))
            ->groupBy('tool')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'tool' => $row->tool,
                'runs' => (int) $row->runs,
                'revenue_usd' => Money::toUsd((int) $row->revenue),
                'cost_usd' => Money::toUsd((int) $row->cost),
                'earned_usd' => Money::toUsd((int) $row->revenue - (int) $row->cost),
            ])
            ->values();

        $byModel = (clone $succeeded)
            ->select('model_key', DB::raw('count(*) as runs'), DB::raw('sum(price_micros) as revenue'))
            ->whereNotNull('model_key')
            ->groupBy('model_key')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'model' => $row->model_key,
                'runs' => (int) $row->runs,
                'revenue_usd' => Money::toUsd((int) $row->revenue),
            ])
            ->values();

        $statuses = Generation::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->select('status', DB::raw('count(*) as n'))
            ->groupBy('status')
            ->pluck('n', 'status');

        return response()->json([
            'range' => $range,
            'margin_percent' => $settings->marginPercent(),
            'kept_share_percent' => $settings->keptShare(),

            // Headline figures for the range, with the same range before it for comparison.
            'kpis' => [
                'revenue_usd' => Money::toUsd($current['revenue']),
                'revenue_prev_usd' => Money::toUsd($previous['revenue']),
                'cost_usd' => Money::toUsd($current['cost']),
                'earned_usd' => Money::toUsd($current['revenue'] - $current['cost']),
                'earned_prev_usd' => Money::toUsd($previous['revenue'] - $previous['cost']),
                'runs' => $current['runs'],
                'runs_prev' => $previous['runs'],
                'success_rate' => $current['runs'] > 0 ? round($current['succeeded'] / $current['runs'] * 100, 1) : null,
                'signups' => $current['signups'],
                'signups_prev' => $previous['signups'],
            ],

            'series' => $series,
            'by_tool' => $byTool,
            'by_model' => $byModel,
            'status' => collect(['succeeded', 'failed', 'running', 'pending'])->map(fn (string $status) => [
                'status' => $status,
                'count' => (int) ($statuses[$status] ?? 0) + ($status === 'running' ? (int) ($statuses['pending'] ?? 0) : 0),
            ])->filter(fn (array $row) => $row['status'] !== 'pending')->values(),

            'recent_runs' => Generation::query()
                ->with('user:id,email')
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->map(fn (Generation $run) => [
                    'id' => $run->id,
                    'tool' => $run->tool,
                    'model_key' => $run->model_key,
                    'status' => $run->status,
                    'user' => $run->user?->email,
                    'price_usd' => Money::toUsd((int) $run->price_micros),
                    'created_at' => $run->created_at?->toIso8601String(),
                ])
                ->values(),

            // Whole-platform figures, not tied to the range.
            'revenue_usd' => Money::toUsd((int) Generation::query()->where('status', Generation::STATUS_SUCCEEDED)->sum('price_micros')),
            'provider_cost_usd' => Money::toUsd((int) Generation::query()->where('status', Generation::STATUS_SUCCEEDED)->sum('cost_micros')),
            'earned_usd' => Money::toUsd((int) Generation::query()->where('status', Generation::STATUS_SUCCEEDED)->sum('price_micros')
                - (int) Generation::query()->where('status', Generation::STATUS_SUCCEEDED)->sum('cost_micros')),
            'runs' => [
                'total' => Generation::query()->count(),
                'succeeded' => Generation::query()->where('status', Generation::STATUS_SUCCEEDED)->count(),
                'failed' => Generation::query()->where('status', Generation::STATUS_FAILED)->count(),
                'running' => Generation::query()->whereIn('status', [Generation::STATUS_PENDING, Generation::STATUS_RUNNING])->count(),
            ],
            'users' => [
                'total' => User::query()->count(),
                'suspended' => User::query()->whereNotNull('suspended_at')->count(),
            ],
            // Prepaid balance users have not spent. It is owed back, so it is not income.
            'credit_held_usd' => Money::toUsd((int) User::query()->sum('balance_micros')),
            'credit_granted_usd' => Money::toUsd((int) LedgerEntry::query()->where('type', LedgerEntry::TYPE_GRANT)->sum('amount_micros')),
            'prices_priced' => ModelPrice::query()->count(),
        ]);
    }

    /**
     * @return array{revenue: int, cost: int, runs: int, succeeded: int, signups: int}
     */
    private function totals(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $runs = Generation::query()->whereBetween('created_at', [$start, $end]);

        return [
            'revenue' => (int) (clone $runs)->where('status', Generation::STATUS_SUCCEEDED)->sum('price_micros'),
            'cost' => (int) (clone $runs)->where('status', Generation::STATUS_SUCCEEDED)->sum('cost_micros'),
            'runs' => (clone $runs)->count(),
            'succeeded' => (clone $runs)->where('status', Generation::STATUS_SUCCEEDED)->count(),
            'signups' => User::query()->withTrashed()->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    /** @return iterable<object{day: string, revenue: int, cost: int, runs: int, failed: int}> */
    private function dailyRuns(CarbonImmutable $start, CarbonImmutable $end): iterable
    {
        return Generation::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('date(created_at) as day')
            ->selectRaw("sum(case when status = 'succeeded' then price_micros else 0 end) as revenue")
            ->selectRaw("sum(case when status = 'succeeded' then coalesce(cost_micros, 0) else 0 end) as cost")
            ->selectRaw('count(*) as runs')
            ->selectRaw("sum(case when status = 'failed' then 1 else 0 end) as failed")
            ->groupBy('day')
            ->get();
    }
}
