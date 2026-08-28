<?php

namespace App\Http\Controllers\Api\V1\Report;

use App\Exports\BookingsReportExport;
use App\Exports\ExpenseReportExport;
use App\Exports\OrdersReportExport;
use App\Exports\RevenueReportExport;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(protected ReportService $reports) {}

    public function revenue(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);
        [$from, $to] = $this->resolveRange($request);

        return $this->success($this->reports->revenue($from, $to, $this->branchId($request)));
    }

    public function bookings(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);
        [$from, $to] = $this->resolveRange($request);

        return $this->success($this->reports->bookings($from, $to, $this->branchId($request)));
    }

    public function orders(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);
        [$from, $to] = $this->resolveRange($request);

        return $this->success($this->reports->orders($from, $to, $this->branchId($request)));
    }

    public function expenses(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);
        [$from, $to] = $this->resolveRange($request);

        return $this->success($this->reports->expenses($from, $to, $this->branchId($request)));
    }

    public function exportRevenue(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);
        [$from, $to] = $this->resolveRange($request);
        $data = $this->reports->revenue($from, $to, $this->branchId($request));

        return Excel::download(new RevenueReportExport($data['breakdown']), "revenue-report.{$this->format($request)}");
    }

    public function exportBookings(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);
        [$from, $to] = $this->resolveRange($request);
        $data = $this->reports->bookings($from, $to, $this->branchId($request));

        return Excel::download(new BookingsReportExport($data['by_status']), "bookings-report.{$this->format($request)}");
    }

    public function exportOrders(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);
        [$from, $to] = $this->resolveRange($request);
        $data = $this->reports->orders($from, $to, $this->branchId($request));

        return Excel::download(new OrdersReportExport($data['by_status']), "orders-report.{$this->format($request)}");
    }

    public function exportExpenses(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);
        [$from, $to] = $this->resolveRange($request);
        $data = $this->reports->expenses($from, $to, $this->branchId($request));

        return Excel::download(new ExpenseReportExport($data['by_category']), "expense-report.{$this->format($request)}");
    }

    protected function resolveRange(Request $request): array
    {
        $from = $request->query('date_from') ?: now()->startOfMonth()->toDateString();
        $to = $request->query('date_to') ?: now()->endOfMonth()->toDateString();

        return [$from, $to];
    }

    protected function branchId(Request $request): ?string
    {
        return $request->query('branch_id') ?: null;
    }

    protected function format(Request $request): string
    {
        return $request->query('format', 'xlsx') === 'csv' ? 'csv' : 'xlsx';
    }
}
