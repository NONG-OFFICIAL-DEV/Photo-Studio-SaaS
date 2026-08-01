<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class AdminAnalyticsService
{
    /**
     * total_tenants/active_tenants/suspended_tenants/mrr/
     * subscriptions_by_status are current-state snapshots ("right now") —
     * a date range can't meaningfully filter those without reconstructing
     * history that isn't tracked, so they always reflect the live count.
     * new_tenants/revenue_collected/signups_trend are period metrics and
     * do respect $dateFrom/$dateTo.
     */
    public function stats(string $dateFrom, string $dateTo): array
    {
        // $dateFrom/$dateTo are date-only strings, but created_at/paid_at are
        // full timestamps — comparing a timestamp column against a bare date
        // string casts it to midnight, silently excluding the entire end
        // day. Widen both ends to full-day bounds before hitting the DB.
        [$from, $to] = $this->dayBounds($dateFrom, $dateTo);

        return [
            'total_tenants' => Tenant::query()->count(),
            'active_tenants' => Tenant::query()->where('is_active', true)->count(),
            'suspended_tenants' => Tenant::query()->where('is_active', false)->count(),
            'mrr' => $this->mrr(),
            'subscriptions_by_status' => $this->subscriptionsByStatus(),
            'new_tenants' => Tenant::query()->whereBetween('created_at', [$from, $to])->count(),
            'revenue_collected' => (float) round(
                SubscriptionPayment::query()->whereBetween('paid_at', [$from, $to])->sum('amount'),
                2
            ),
            'signups_trend' => $this->signupsTrend($dateFrom, $dateTo),
        ];
    }

    protected function dayBounds(string $dateFrom, string $dateTo): array
    {
        return [Carbon::parse($dateFrom)->startOfDay(), Carbon::parse($dateTo)->endOfDay()];
    }

    /**
     * Monthly Recurring Revenue — each Active subscription's charge
     * normalized to a monthly figure via its billing cycle. Falls back to
     * the plan's list price for that cycle when the subscription itself
     * has no recorded `amount` (true for every trial-born subscription
     * that hasn't gone through a real billing event yet).
     */
    protected function mrr(): float
    {
        return (float) Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->with('plan')
            ->get()
            ->sum(function (Subscription $subscription) {
                $cycle = $subscription->billing_cycle ?? BillingCycle::Monthly;
                $amount = $subscription->amount ?? $this->planPriceForCycle($subscription, $cycle);

                return $amount ? round((float) $amount / $cycle->months(), 2) : 0;
            });
    }

    protected function planPriceForCycle(Subscription $subscription, BillingCycle $cycle): ?float
    {
        $plan = $subscription->plan;

        if (! $plan) {
            return null;
        }

        return (float) match ($cycle) {
            BillingCycle::Monthly => $plan->price_monthly,
            BillingCycle::Quarterly => $plan->price_quarterly,
            BillingCycle::Yearly => $plan->price_yearly,
        };
    }

    protected function subscriptionsByStatus(): array
    {
        $counts = Subscription::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(SubscriptionStatus::cases())
            ->mapWithKeys(fn (SubscriptionStatus $status) => [
                $status->value => (int) ($counts[$status->value] ?? 0),
            ])
            ->all();
    }

    /**
     * Bucketed by day when the selected range is a month or less (a
     * readable daily trend), otherwise by month — same adaptive
     * granularity ReportService::revenueBreakdown() uses, so a
     * year-long range doesn't render as an unreadable wall of daily
     * points. Numeric labels only (MM/yyyy or dd/MM), never a month
     * name — this app's standing convention (see BookingCalendar.vue)
     * to avoid needing per-locale month-name translation.
     */
    protected function signupsTrend(string $dateFrom, string $dateTo): array
    {
        $groupByMonth = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) > 31;
        $sqlFormat = $groupByMonth ? 'YYYY-MM' : 'YYYY-MM-DD';
        $labelFormat = $groupByMonth ? 'm/Y' : 'd/m';
        [$from, $to] = $this->dayBounds($dateFrom, $dateTo);

        $counts = Tenant::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("to_char(created_at, '{$sqlFormat}') as period, count(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $cursor = Carbon::parse($dateFrom)->startOf($groupByMonth ? 'month' : 'day');
        $end = Carbon::parse($dateTo);
        $step = $groupByMonth ? 'addMonth' : 'addDay';
        $periodFormat = $groupByMonth ? 'Y-m' : 'Y-m-d';

        $trend = [];
        while ($cursor->lte($end)) {
            $key = $cursor->format($periodFormat);
            $trend[] = ['label' => $cursor->format($labelFormat), 'value' => (int) ($counts[$key] ?? 0)];
            $cursor->{$step}();
        }

        return $trend;
    }
}
