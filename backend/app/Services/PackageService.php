<?php

namespace App\Services;

use App\Models\Package;
use App\Models\User;
use App\Repositories\Contracts\PackageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PackageService extends BaseService
{
    public function __construct(protected PackageRepositoryInterface $packages)
    {
        parent::__construct($packages);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->packages->paginateServer($filters);
    }

    public function create(array $data, ?User $creator = null): Package
    {
        $components = $data['components'] ?? [];
        unset($data['components']);

        return DB::transaction(function () use ($data, $components, $creator) {
            /** @var Package $package */
            $package = $this->packages->create([
                ...$data,
                'created_by' => $creator?->id,
            ]);

            $package->components()->createMany($this->normalizeComponents($components));

            return $package->load('components.service', 'components.addon');
        });
    }

    public function update(Package $package, array $data): Package
    {
        $components = $data['components'] ?? null;
        unset($data['components']);

        return DB::transaction(function () use ($package, $data, $components) {
            $this->packages->update($package, $data);

            if ($components !== null) {
                $package->components()->delete();
                $package->components()->createMany($this->normalizeComponents($components));
            }

            return $package->fresh(['components.service', 'components.addon']);
        });
    }

    public function delete(Package $package): bool
    {
        return $this->packages->delete($package);
    }

    /**
     * The Telegram quote message sent to a customer for a package —
     * plain text only (no PDF/image render, unlike invoices): a package
     * has no QR/payment step of its own, just a price and a components
     * list, so a formatted message is the whole deliverable.
     */
    public function packageSummaryText(Package $package): string
    {
        $package->loadMissing(['components.service', 'components.addon', 'tenant']);

        $lines = [
            "🎁 {$package->name}",
            $package->tenant->name,
        ];

        if ($package->description) {
            $lines[] = '';
            $lines[] = $package->description;
        }

        if ($package->components->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Includes:';

            foreach ($package->components as $component) {
                $suffix = $component->is_optional ? ' (optional)' : '';
                $lines[] = "• {$component->name} x{$component->quantity}{$suffix}";
            }
        }

        $lines[] = '';
        $lines[] = 'Price: $'.number_format((float) $package->final_price, 2);
        $lines[] = '';
        $lines[] = 'Interested? Reply here or contact us to book!';

        return implode("\n", $lines);
    }

    protected function normalizeComponents(array $components): array
    {
        return array_map(fn (array $component) => [
            'service_id' => $component['service_id'] ?? null,
            'addon_id' => $component['addon_id'] ?? null,
            'quantity' => max(1, (int) ($component['quantity'] ?? 1)),
            'is_optional' => (bool) ($component['is_optional'] ?? false),
        ], $components);
    }
}
