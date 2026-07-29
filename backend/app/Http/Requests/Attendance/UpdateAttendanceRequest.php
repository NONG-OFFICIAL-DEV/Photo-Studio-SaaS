<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('attendanceRecord'));
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(AttendanceStatus::class)],
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after:clock_in_at'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
