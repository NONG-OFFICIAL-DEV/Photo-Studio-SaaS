<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(protected InvoiceService $invoices)
    {
    }

    public function store(StorePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->recordPayment($invoice, $request->validated(), $request->user());

        return $this->created(new InvoiceResource($invoice), 'Payment recorded successfully.');
    }

    public function destroy(Invoice $invoice, Payment $payment): JsonResponse
    {
        $this->authorize('deletePayment', $invoice);

        $invoice = $this->invoices->deletePayment($invoice, $payment);

        return $this->success(new InvoiceResource($invoice), 'Payment removed successfully.');
    }
}
