<?php

namespace App\Services;

use App\Enums\EditingStatus;
use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\EditingTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;

class DashboardService
{
    public function stats(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        return [
            'today_revenue' => (float) Payment::whereDate('paid_at', $today)->sum('amount'),
            'monthly_revenue' => (float) Payment::whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount'),
            'new_customers' => Customer::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
            'bookings' => Booking::whereBetween('starts_at', [$monthStart, $monthEnd])->count(),
            'pending_editing' => EditingTask::where('status', '!=', EditingStatus::Completed->value)->count(),
            'ready_for_delivery' => Order::where('status', OrderStatus::ReadyForDelivery->value)->count(),
            'completed_orders' => Order::where('status', OrderStatus::Delivered->value)
                ->whereBetween('updated_at', [$monthStart, $monthEnd])
                ->count(),
            'top_services' => $this->topServices($monthStart, $monthEnd),
            'revenue_trend' => $this->revenueTrend(),
        ];
    }

    protected function topServices(string $monthStart, string $monthEnd): array
    {
        return OrderItem::query()
            ->whereNotNull('service_id')
            ->whereHas('order', fn ($query) => $query->whereBetween('created_at', [$monthStart, $monthEnd]))
            ->selectRaw('service_id, SUM(line_total) as revenue, SUM(quantity) as quantity')
            ->groupBy('service_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->with('service')
            ->get()
            ->map(fn (OrderItem $row) => [
                'name' => $row->service?->name ?? 'Unknown',
                'revenue' => round((float) $row->revenue, 2),
                'quantity' => (int) $row->quantity,
            ])
            ->values()
            ->all();
    }

    protected function revenueTrend(): array
    {
        return collect(range(5, 0))
            ->map(function (int $monthsAgo) {
                $date = now()->subMonths($monthsAgo);
                $sum = (float) Payment::whereYear('paid_at', $date->year)
                    ->whereMonth('paid_at', $date->month)
                    ->sum('amount');

                return ['label' => $date->format('M'), 'value' => round($sum, 2)];
            })
            ->values()
            ->all();
    }
}
