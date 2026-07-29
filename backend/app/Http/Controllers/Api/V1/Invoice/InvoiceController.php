<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Requests\Invoice\VoidInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(protected InvoiceService $invoices)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $paginator = $this->invoices->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage', 'status', 'customer_id', 'order_id',
        ]));

        return $this->success(
            InvoiceResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->create($request->validated(), $request->user());

        return $this->created(new InvoiceResource($invoice), 'Invoice created successfully.');
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load('items', 'customer', 'order', 'payments.recordedBy');

        return $this->success(new InvoiceResource($invoice));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->update($invoice, $request->validated());

        return $this->success(new InvoiceResource($invoice), 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $this->invoices->delete($invoice);

        return $this->noContent('Invoice deleted successfully.');
    }

    public function send(Invoice $invoice): JsonResponse
    {
        $this->authorize('send', $invoice);

        return $this->success(new InvoiceResource($this->invoices->send($invoice)), 'Invoice sent.');
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->void($invoice, $request->string('reason')->toString());

        return $this->success(new InvoiceResource($invoice), 'Invoice voided.');
    }
}
