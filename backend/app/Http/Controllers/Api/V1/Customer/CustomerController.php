<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exports\CustomersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\BlacklistCustomerRequest;
use App\Http\Requests\Customer\ImportCustomersRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Imports\CustomersImport;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(protected CustomerService $customers)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $paginator = $this->customers->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage',
            'tag_id', 'is_favorite', 'is_blacklisted', 'gender',
        ]));

        return $this->success(
            CustomerResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->validated(), $request->user());

        return $this->created(new CustomerResource($customer), 'Customer created successfully.');
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $customer->load(['tags', 'notes.user', 'createdBy']);

        return $this->success(new CustomerResource($customer));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customers->update($customer, $request->validated());

        return $this->success(new CustomerResource($customer), 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $this->customers->delete($customer);

        return $this->noContent('Customer deleted successfully.');
    }

    public function toggleFavorite(Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $customer = $this->customers->toggleFavorite($customer);

        return $this->success(new CustomerResource($customer), 'Customer updated successfully.');
    }

    public function blacklist(BlacklistCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customers->blacklist($customer, $request->string('reason')->toString());

        return $this->success(new CustomerResource($customer), 'Customer blacklisted.');
    }

    public function unblacklist(Customer $customer): JsonResponse
    {
        $this->authorize('blacklist', $customer);

        $customer = $this->customers->unblacklist($customer);

        return $this->success(new CustomerResource($customer), 'Customer removed from blacklist.');
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('export', Customer::class);

        $format = $request->query('format', 'xlsx') === 'csv' ? 'csv' : 'xlsx';
        $filters = $request->only(['search', 'is_favorite', 'is_blacklisted']);

        return Excel::download(new CustomersExport($filters), "customers.{$format}");
    }

    public function import(ImportCustomersRequest $request): JsonResponse
    {
        $import = new CustomersImport($request->user());

        Excel::import($import, $request->file('file'));

        $failures = collect($import->failures())->map(fn ($failure) => [
            'row' => $failure->row(),
            'attribute' => $failure->attribute(),
            'errors' => $failure->errors(),
        ]);

        return $this->success([
            'imported' => $import->importedCount(),
            'failed' => $failures->count(),
            'failures' => $failures->values(),
        ], 'Import completed.');
    }
}
