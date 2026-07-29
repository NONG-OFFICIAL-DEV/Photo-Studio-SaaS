<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Repositories\Contracts\AttendanceRecordRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttendanceService extends BaseService
{
    public function __construct(protected AttendanceRecordRepositoryInterface $attendance)
    {
        parent::__construct($attendance);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->attendance->paginateServer($filters);
    }

    public function clockIn(User $user): AttendanceRecord
    {
        $today = now()->toDateString();
        $record = AttendanceRecord::where('user_id', $user->id)->whereDate('date', $today)->first();

        if ($record && $record->clock_in_at) {
            throw new HttpException(422, 'You have already clocked in today.');
        }

        $now = now();
        $expected = Carbon::parse($today.' '.config('attendance.expected_start_time', '09:00'));
        $status = $now->greaterThan($expected) ? AttendanceStatus::Late : AttendanceStatus::Present;

        if ($record) {
            $record->update(['clock_in_at' => $now, 'status' => $status]);

            return $record->fresh('user');
        }

        $record = $this->attendance->create([
            'user_id' => $user->id,
            'date' => $today,
            'clock_in_at' => $now,
            'status' => $status,
        ]);

        return $record->load('user');
    }

    public function clockOut(User $user): AttendanceRecord
    {
        $today = now()->toDateString();
        $record = AttendanceRecord::where('user_id', $user->id)->whereDate('date', $today)->first();

        if (! $record || ! $record->clock_in_at) {
            throw new HttpException(422, 'You must clock in before you can clock out.');
        }

        if ($record->clock_out_at) {
            throw new HttpException(422, 'You have already clocked out today.');
        }

        $record->update(['clock_out_at' => now()]);

        return $record->fresh('user');
    }

    /**
     * Manager-recorded entry — either a full backdated record (with clock
     * times) or an absence marker (status=absent, no clock times).
     */
    public function create(array $data, ?User $creator = null): AttendanceRecord
    {
        $existing = AttendanceRecord::where('user_id', $data['user_id'])->whereDate('date', $data['date'])->first();

        if ($existing) {
            throw new HttpException(422, 'An attendance record already exists for this employee on this date.');
        }

        $record = $this->attendance->create([
            ...$data,
            'created_by' => $creator?->id,
        ]);

        return $record->load('user');
    }

    public function update(AttendanceRecord $record, array $data): AttendanceRecord
    {
        $this->attendance->update($record, $data);

        return $record->fresh('user');
    }

    public function delete(AttendanceRecord $record): bool
    {
        return $this->attendance->delete($record);
    }
}
