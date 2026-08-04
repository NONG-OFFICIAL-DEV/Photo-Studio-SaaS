<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BillingCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmPaymentClaimRequest;
use App\Http\Requests\Admin\RejectPaymentClaimRequest;
use App\Http\Resources\PaymentConfirmationResource;
use App\Models\PaymentConfirmation;
use App\Services\PaymentConfirmationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated purely by the `super-admin` route middleware, same convention as
 * AdminPlanController/AdminPlatformSettingController.
 */
class AdminPaymentConfirmationController extends Controller
{
    use ApiResponse;

    public function __construct(protected PaymentConfirmationService $paymentConfirmations)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paymentConfirmations->pending();

        return $this->success(
            PaymentConfirmationResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function confirm(ConfirmPaymentClaimRequest $request, PaymentConfirmation $claim): JsonResponse
    {
        $cycle = $request->validated('billing_cycle') ? BillingCycle::from($request->validated('billing_cycle')) : null;
        $claim = $this->paymentConfirmations->confirm($claim, $request->user(), $cycle);

        return $this->success(new PaymentConfirmationResource($claim), 'Payment claim confirmed and subscription renewed.');
    }

    public function reject(RejectPaymentClaimRequest $request, PaymentConfirmation $claim): JsonResponse
    {
        $claim = $this->paymentConfirmations->reject($claim, $request->user(), $request->validated('note'));

        return $this->success(new PaymentConfirmationResource($claim), 'Payment claim rejected.');
    }
}
