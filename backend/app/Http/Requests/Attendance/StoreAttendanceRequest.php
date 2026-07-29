<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AttendanceRecord::class);
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required', 'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after:clock_in_at'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
