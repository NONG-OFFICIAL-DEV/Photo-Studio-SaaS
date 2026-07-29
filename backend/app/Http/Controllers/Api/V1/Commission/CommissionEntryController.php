<?php

namespace App\Http\Controllers\Api\V1\Commission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\StoreCommissionEntryRequest;
use App\Http\Requests\Commission\UpdateCommissionEntryRequest;
use App\Http\Resources\CommissionEntryResource;
use App\Models\CommissionEntry;
use App\Services\CommissionEntryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionEntryController extends Controller
{
    use ApiResponse;

    public function __construct(protected CommissionEntryService $commissions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommissionEntry::class);

        $paginator = $this->commissions->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage', 'user_id', 'date_from', 'date_to',
        ]));

        return $this->success(
            CommissionEntryResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreCommissionEntryRequest $request): JsonResponse
    {
        $entry = $this->commissions->create($request->validated(), $request->user());

        return $this->created(new CommissionEntryResource($entry), 'Commission entry recorded successfully.');
    }

    public function update(UpdateCommissionEntryRequest $request, CommissionEntry $commissionEntry): JsonResponse
    {
        $entry = $this->commissions->update($commissionEntry, $request->validated());

        return $this->success(new CommissionEntryResource($entry), 'Commission entry updated successfully.');
    }

    public function destroy(CommissionEntry $commissionEntry): JsonResponse
    {
        $this->authorize('delete', $commissionEntry);

        $this->commissions->delete($commissionEntry);

        return $this->noContent('Commission entry deleted successfully.');
    }
}
