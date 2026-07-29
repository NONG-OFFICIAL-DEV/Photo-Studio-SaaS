<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollEntryRequest;
use App\Http\Requests\Payroll\UpdatePayrollEntryRequest;
use App\Http\Resources\PayrollEntryResource;
use App\Models\PayrollEntry;
use App\Services\PayrollEntryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollEntryController extends Controller
{
    use ApiResponse;

    public function __construct(protected PayrollEntryService $payroll)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PayrollEntry::class);

        $paginator = $this->payroll->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage', 'user_id', 'status',
        ]));

        return $this->success(
            PayrollEntryResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StorePayrollEntryRequest $request): JsonResponse
    {
        $entry = $this->payroll->create($request->validated(), $request->user());

        return $this->created(new PayrollEntryResource($entry), 'Payroll entry created successfully.');
    }

    public function show(PayrollEntry $payrollEntry): JsonResponse
    {
        $this->authorize('view', $payrollEntry);

        return $this->success(new PayrollEntryResource($payrollEntry->load('user')));
    }

    public function update(UpdatePayrollEntryRequest $request, PayrollEntry $payrollEntry): JsonResponse
    {
        $entry = $this->payroll->update($payrollEntry, $request->validated());

        return $this->success(new PayrollEntryResource($entry), 'Payroll entry updated successfully.');
    }

    public function destroy(PayrollEntry $payrollEntry): JsonResponse
    {
        $this->authorize('delete', $payrollEntry);

        $this->payroll->delete($payrollEntry);

        return $this->noContent('Payroll entry deleted successfully.');
    }

    public function pay(PayrollEntry $payrollEntry): JsonResponse
    {
        $this->authorize('pay', $payrollEntry);

        $entry = $this->payroll->markPaid($payrollEntry);

        return $this->success(new PayrollEntryResource($entry), 'Payroll entry marked as paid.');
    }
}
