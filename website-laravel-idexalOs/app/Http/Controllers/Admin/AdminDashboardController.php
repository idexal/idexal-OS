<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GatewayRequest;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $monthStart = now()->startOfMonth();
        $subscriptions = Subscription::query()
            ->with('plan')
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->get();

        $mrr = $subscriptions->sum(function (Subscription $subscription): float {
            $plan = $subscription->plan;

            if ($plan === null) {
                return 0.0;
            }

            $price = $plan->priceFor($subscription->billing_cycle);

            return $subscription->billing_cycle === 'yearly' ? $price / 12 : $price;
        });

        $usage = GatewayRequest::query()
            ->whereBetween('created_at', [$monthStart, now()])
            ->selectRaw('COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(cost), 0) as cost')
            ->first();

        return view('admin.dashboard', [
            'stats' => [
                'organizations' => Organization::query()->where('status', Organization::STATUS_ACTIVE)->count(),
                'users' => User::query()->count(),
                'activeSubscriptions' => $subscriptions->count(),
                'mrr' => $mrr,
                'monthRevenue' => Payment::query()
                    ->where('status', Payment::STATUS_SUCCEEDED)
                    ->whereBetween('paid_at', [$monthStart, now()])
                    ->sum('amount'),
                'monthRequests' => (int) ($usage->requests ?? 0),
                'monthTokens' => (int) ($usage->tokens ?? 0),
                'monthGatewayCost' => (float) ($usage->cost ?? 0),
                'openInvoices' => Invoice::query()->where('status', Invoice::STATUS_OPEN)->count(),
                'desktopSessions' => DB::table('desktop_sessions')->whereNull('revoked_at')->count(),
            ],
            'signupSeries' => $this->dailyCounts(User::query(), 30),
            'requestSeries' => $this->dailyCounts(GatewayRequest::query(), 30),
            'revenueSeries' => $this->monthlyRevenue(),
            'recentUsers' => User::query()->latest('created_at')->limit(6)->get(),
            'recentInvoices' => Invoice::query()->with('organization:id,name,slug')->latest('created_at')->limit(6)->get(),
            'recentRequests' => GatewayRequest::query()
                ->with(['organization:id,name,slug', 'aiModel:id,display_name'])
                ->latest('created_at')
                ->limit(8)
                ->get(),
            'topOrganizations' => GatewayRequest::query()
                ->whereBetween('created_at', [$monthStart, now()])
                ->selectRaw('organization_id, COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(cost), 0) as cost')
                ->with('organization:id,name,slug')
                ->groupBy('organization_id')
                ->orderByDesc('requests')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function dailyCounts(object $query, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = $query
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $labels[] = $date->translatedFormat('d M');
            $values[] = (int) ($rows[$date->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    private function monthlyRevenue(): array
    {
        $from = now()->subMonths(5)->startOfMonth();
        $totals = Payment::query()
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $from)
            ->get(['paid_at', 'amount'])
            ->groupBy(fn (Payment $payment): string => $payment->paid_at->format('Y-m'))
            ->map(fn ($payments): float => (float) $payments->sum('amount'));

        $labels = [];
        $values = [];

        for ($i = 0; $i < 6; $i++) {
            $date = $from->copy()->addMonths($i);
            $labels[] = $date->translatedFormat('M Y');
            $values[] = round((float) ($totals[$date->format('Y-m')] ?? 0), 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
