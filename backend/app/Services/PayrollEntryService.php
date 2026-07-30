<?php

namespace App\Services;

use App\Enums\PayrollStatus;
use App\Enums\PayType;
use App\Exceptions\ApiException;
use App\Models\AttendanceRecord;
use App\Models\CommissionEntry;
use App\Models\PayrollEntry;
use App\Models\User;
use App\Repositories\Contracts\PayrollEntryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PayrollEntryService extends BaseService
{
    public function __construct(protected PayrollEntryRepositoryInterface $payroll)
    {
        parent::__construct($payroll);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->payroll->paginateServer($filters);
    }

    /**
     * base_pay/commission_total default to values computed from the
     * employee's pay profile and their attendance/commission records for
     * the period — but can be overridden at creation time (e.g. a manual
     * adjustment). Once created, these are a financial snapshot: later
     * attendance/commission edits never retroactively change a payroll
     * entry (unlike Package pricing, which is always live).
     */
    public function create(array $data, ?User $creator = null): PayrollEntry
    {
        /** @var User $user */
        $user = User::query()->findOrFail($data['user_id']);

        $basePay = array_key_exists('base_pay', $data) && $data['base_pay'] !== null
            ? round((float) $data['base_pay'], 2)
            : $this->computeBasePay($user, $data['period_start'], $data['period_end']);

        $commissionTotal = array_key_exists('commission_total', $data) && $data['commission_total'] !== null
            ? round((float) $data['commission_total'], 2)
            : $this->sumCommission($user, $data['period_start'], $data['period_end']);

        $deductions = round((float) ($data['deductions'] ?? 0), 2);
        $netPay = max(0, round($basePay + $commissionTotal - $deductions, 2));

        $entry = $this->payroll->create([
            ...$data,
            'base_pay' => $basePay,
            'commission_total' => $commissionTotal,
            'deductions' => $deductions,
            'net_pay' => $netPay,
            'created_by' => $creator?->id,
        ]);

        return $entry->load('user');
    }

    public function update(PayrollEntry $entry, array $data): PayrollEntry
    {
        if ($entry->status === PayrollStatus::Paid) {
            throw new ApiException(422, 'A paid payroll entry can no longer be edited.', 'PAYROLL_ENTRY_EDIT_LOCKED');
        }

        $basePay = round((float) ($data['base_pay'] ?? $entry->base_pay), 2);
        $commissionTotal = round((float) ($data['commission_total'] ?? $entry->commission_total), 2);
        $deductions = round((float) ($data['deductions'] ?? $entry->deductions), 2);
        $netPay = max(0, round($basePay + $commissionTotal - $deductions, 2));

        $this->payroll->update($entry, [
            ...$data,
            'base_pay' => $basePay,
            'commission_total' => $commissionTotal,
            'deductions' => $deductions,
            'net_pay' => $netPay,
        ]);

        return $entry->fresh('user');
    }

    public function delete(PayrollEntry $entry): bool
    {
        if ($entry->status === PayrollStatus::Paid) {
            throw new ApiException(422, 'A paid payroll entry can no longer be deleted.', 'PAYROLL_ENTRY_DELETE_LOCKED');
        }

        return $this->payroll->delete($entry);
    }

    public function markPaid(PayrollEntry $entry): PayrollEntry
    {
        if ($entry->status === PayrollStatus::Paid) {
            throw new ApiException(422, 'This payroll entry has already been paid.', 'PAYROLL_ENTRY_ALREADY_PAID');
        }

        $entry->update(['status' => PayrollStatus::Paid, 'paid_at' => now()]);

        return $entry;
    }

    protected function computeBasePay(User $user, string $periodStart, string $periodEnd): float
    {
        if ($user->pay_type === PayType::Hourly) {
            $hours = AttendanceRecord::where('user_id', $user->id)
                ->whereBetween('date', [$periodStart, $periodEnd])
                ->get()
                ->sum(fn (AttendanceRecord $record) => $record->hours_worked ?? 0);

            return round($hours * (float) $user->base_pay, 2);
        }

        return round((float) $user->base_pay, 2);
    }

    protected function sumCommission(User $user, string $periodStart, string $periodEnd): float
    {
        return round((float) CommissionEntry::where('user_id', $user->id)
            ->whereBetween('earned_date', [$periodStart, $periodEnd])
            ->sum('amount'), 2);
    }
}
