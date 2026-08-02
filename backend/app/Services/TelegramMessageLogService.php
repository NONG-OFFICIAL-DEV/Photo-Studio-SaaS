<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\TelegramMessageLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Records every Telegram send attempt (invoice/album-photos/package) and
 * serves it back as a filterable history — the toast shown at send time
 * is the only feedback that existed before this, so a staff member who
 * missed it (or wasn't the one who clicked send) had no way to check
 * whether something actually reached the customer.
 */
class TelegramMessageLogService
{
    public function record(
        Customer $customer,
        string $type,
        ?string $subjectLabel,
        ?string $format,
        bool $success,
        ?string $errorMessage,
        ?User $sentBy
    ): TelegramMessageLog {
        return TelegramMessageLog::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'type' => $type,
            'subject_label' => $subjectLabel,
            'format' => $format,
            'status' => $success ? 'sent' : 'failed',
            // Not force-nulled on success — a partial-success batch send
            // (some files delivered, some rejected) still needs to surface
            // which ones failed even though the overall status is 'sent'.
            'error_message' => $errorMessage,
            'sent_by' => $sentBy?->id,
            'sent_by_name' => $sentBy?->name,
        ]);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate((int) ($filters['perPage'] ?? 15))->withQueryString();
    }

    public function forCustomer(string $customerId, array $filters): LengthAwarePaginator
    {
        return $this->query([...$filters, 'customer_id' => $customerId])->paginate((int) ($filters['perPage'] ?? 15))->withQueryString();
    }

    protected function query(array $filters): Builder
    {
        $query = TelegramMessageLog::query()->latest('created_at');

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
