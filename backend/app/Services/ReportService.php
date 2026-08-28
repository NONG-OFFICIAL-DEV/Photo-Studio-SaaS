<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class ReportService
{
    public function revenue(string $dateFrom, string $dateTo, ?string $branchId = null): array
    {
        $totalInvoiced = (float) Invoice::whereBetween('issue_date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('total');
        $totalCollected = (float) Payment::whereBetween('paid_at', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('amount');

        return [
            'total_invoiced' => round($totalInvoiced, 2),
            'total_collected' => round($totalCollected, 2),
            'outstanding' => round(max(0, $totalInvoiced - $totalCollected), 2),
            'breakdown' => $this->revenueBreakdown($dateFrom, $dateTo, $branchId),
        ];
    }

    /**
     * Grouped by day when the range is a month or less (for a readable
     * daily trend), otherwise by month (so a year-long range doesn't
     * return hundreds of daily rows). Shared by every *Breakdown() method
     * below so all four reports switch granularity at the same threshold.
     */
    protected function periodFormat(string $dateFrom, string $dateTo): string
    {
        $groupByMonth = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) > 31;

        return $groupByMonth ? 'YYYY-MM' : 'YYYY-MM-DD';
    }

    protected function revenueBreakdown(string $dateFrom, string $dateTo, ?string $branchId = null): array
    {
        $format = $this->periodFormat($dateFrom, $dateTo);

        $invoiced = Invoice::whereBetween('issue_date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw("to_char(issue_date, '{$format}') as period, SUM(total) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $collected = Payment::whereBetween('paid_at', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw("to_char(paid_at, '{$format}') as period, SUM(amount) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $periods = collect($invoiced->keys())->merge($collected->keys())->unique()->sort()->values();

        return $periods->map(fn ($period) => [
            'period' => $period,
            'invoiced' => round((float) ($invoiced[$period] ?? 0), 2),
            'collected' => round((float) ($collected[$period] ?? 0), 2),
        ])->all();
    }

    public function bookings(string $dateFrom, string $dateTo, ?string $branchId = null): array
    {
        $rangeEnd = Carbon::parse($dateTo)->endOfDay();
        $query = Booking::whereBetween('starts_at', [$dateFrom, $rangeEnd])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $byType = (clone $query)->selectRaw('type, COUNT(*) as count')->groupBy('type')->pluck('count', 'type');
        $byStatus = (clone $query)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');

        return [
            'total' => (clone $query)->count(),
            'by_type' => collect(BookingType::cases())->map(fn ($case) => [
                'type' => $case->value,
                'label' => $case->label(),
                'count' => (int) ($byType[$case->value] ?? 0),
            ])->all(),
            'by_status' => collect(BookingStatus::cases())->map(fn ($case) => [
                'status' => $case->value,
                'label' => $case->label(),
                'count' => (int) ($byStatus[$case->value] ?? 0),
            ])->all(),
            'breakdown' => $this->bookingsBreakdown($dateFrom, $dateTo, $branchId),
        ];
    }

    protected function bookingsBreakdown(string $dateFrom, string $dateTo, ?string $branchId = null): array
    {
        $format = $this->periodFormat($dateFrom, $dateTo);
        $rangeEnd = Carbon::parse($dateTo)->endOfDay();

        return Booking::whereBetween('starts_at', [$dateFrom, $rangeEnd])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw("to_char(starts_at, '{$format}') as period, COUNT(*) as count")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['period' => $row->period, 'count' => (int) $row->count])
            ->all();
    }

    public function orders(string $dateFrom, string $dateTo, ?string $branchId = null): array
    {
        $rangeEnd = Carbon::parse($dateTo)->endOfDay();
        $query = Order::whereBetween('created_at', [$dateFrom, $rangeEnd])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count, SUM(total) as value')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'total_count' => (clone $query)->count(),
            'total_value' => round((float) (clone $query)->sum('total'), 2),
            'by_status' => collect(OrderStatus::cases())->map(function ($case) use ($byStatus) {
                $row = $byStatus->get($case->value);

                return [
                    'status' => $case->value,
                    'label' => $case->label(),
                    'count' => (int) ($row->count ?? 0),
                    'value' => round((float) ($row->value ?? 0), 2),
                ];
            })->all(),
            'breakdown' => $this->ordersBreakdown($dateFrom, $rangeEnd, $branchId),
        ];
    }

    protected function ordersBreakdown(string $dateFrom, Carbon $rangeEnd, ?string $branchId = null): array
    {
        $format = $this->periodFormat($dateFrom, $rangeEnd->toDateString());

        return Order::whereBetween('created_at', [$dateFrom, $rangeEnd])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw("to_char(created_at, '{$format}') as period, COUNT(*) as count, SUM(total) as value")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['period' => $row->period, 'count' => (int) $row->count, 'value' => round((float) $row->value, 2)])
            ->all();
    }

    public function expenses(string $dateFrom, string $dateTo, ?string $branchId = null): array
    {
        $query = Expense::whereBetween('expense_date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $byCategory = (clone $query)
            ->leftJoin('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->selectRaw("COALESCE(expense_categories.name, 'Uncategorized') as category, SUM(expenses.amount) as total")
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return [
            'total' => round((float) (clone $query)->sum('amount'), 2),
            'by_category' => $byCategory->map(fn ($row) => [
                'category' => $row->category,
                'amount' => round((float) $row->total, 2),
            ])->all(),
            'breakdown' => $this->expensesBreakdown($dateFrom, $dateTo, $branchId),
        ];
    }

    protected function expensesBreakdown(string $dateFrom, string $dateTo, ?string $branchId = null): array
    {
        $format = $this->periodFormat($dateFrom, $dateTo);

        return Expense::whereBetween('expense_date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw("to_char(expense_date, '{$format}') as period, SUM(amount) as total")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['period' => $row->period, 'total' => round((float) $row->total, 2)])
            ->all();
    }
}
