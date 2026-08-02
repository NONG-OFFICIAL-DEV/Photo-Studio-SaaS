<?php

namespace App\Http\Controllers\Api\V1\Package;

use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SendPackageTelegramRequest;
use App\Http\Requests\Package\StorePackageRequest;
use App\Http\Requests\Package\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Customer;
use App\Models\Package;
use App\Services\PackageService;
use App\Services\TelegramMessageLogService;
use App\Services\TelegramService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    use ApiResponse;

    public function __construct(protected PackageService $packages, protected TelegramMessageLogService $telegramLogs)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Package::class);

        $paginator = $this->packages->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage', 'is_active',
        ]));

        return $this->success(
            PackageResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = $this->packages->create($request->validated(), $request->user());

        return $this->created(new PackageResource($package), 'Package created successfully.');
    }

    public function show(Package $package): JsonResponse
    {
        $this->authorize('view', $package);

        return $this->success(new PackageResource($package->load('components.service', 'components.addon')));
    }

    public function update(UpdatePackageRequest $request, Package $package): JsonResponse
    {
        $package = $this->packages->update($package, $request->validated());

        return $this->success(new PackageResource($package), 'Package updated successfully.');
    }

    public function destroy(Package $package): JsonResponse
    {
        $this->authorize('delete', $package);

        $this->packages->delete($package);

        return $this->noContent('Package deleted successfully.');
    }

    /**
     * Sends the package's name/description/price/components as a plain
     * Telegram text message — a quote/offer, not a delivery, so unlike
     * invoices there's no PDF/image render or QR step; the message itself
     * is the whole deliverable. $customer_id is a plain body field (not
     * route-bound) since a Package has no customer of its own — it's
     * picked fresh on every send.
     */
    public function sendTelegram(SendPackageTelegramRequest $request, Package $package, TelegramService $telegram): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant->telegramConnected()) {
            return $this->error('Connect a Telegram bot in Settings first.', 422, [], 'TELEGRAM_NOT_CONFIGURED');
        }

        $customer = Customer::findOrFail($request->validated()['customer_id']);

        if (! $customer->telegram_chat_id) {
            return $this->error('This customer has not connected Telegram yet.', 422, [], 'TELEGRAM_CUSTOMER_NOT_LINKED');
        }

        $result = $telegram->sendMessage(
            $tenant->telegram_bot_token,
            $customer->telegram_chat_id,
            $this->packages->packageSummaryText($package)
        );

        if (! ($result['ok'] ?? false)) {
            $this->telegramLogs->record($customer, 'package', $package->name, null, false, $result['description'] ?? 'Failed to send via Telegram.', $request->user());

            return $this->error('Failed to send via Telegram.', 502, [], 'TELEGRAM_SEND_FAILED');
        }

        $this->telegramLogs->record($customer, 'package', $package->name, null, true, null, $request->user());

        return $this->success(null, 'Package sent via Telegram.');
    }
}
