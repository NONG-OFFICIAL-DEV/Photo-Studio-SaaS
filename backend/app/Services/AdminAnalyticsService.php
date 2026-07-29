<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Tenant;

class AdminAnalyticsService
{
    public function stats(): array
    {
        return [
            'total_tenants' => Tenant::query()->count(),
            'active_tenants' => Tenant::query()->where('is_active', true)->count(),
            'suspended_tenants' => Tenant::query()->where('is_active', false)->count(),
            'mrr' => $this->mrr(),
            'subscriptions_by_status' => $this->subscriptionsByStatus(),
            'signups_trend' => $this->signupsTrend(),
        ];
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

    protected function signupsTrend(): array
    {
        return collect(range(5, 0))
            ->map(function (int $monthsAgo) {
                $date = now()->subMonths($monthsAgo);
                $count = Tenant::query()
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count();

                return ['label' => $date->format('M'), 'value' => $count];
            })
            ->values()
            ->all();
    }
}
