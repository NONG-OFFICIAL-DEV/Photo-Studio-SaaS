<?php

namespace App\Http\Requests\Booking;

use App\Enums\BookingType;
use App\Enums\LocationType;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('booking'));
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'sometimes', 'required', 'uuid',
                Rule::exists('customers', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'assigned_user_id' => [
                'nullable', 'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'branch_id' => [
                'sometimes', 'nullable', 'uuid',
                Rule::exists('branches', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'type' => ['sometimes', 'required', Rule::in(array_column(BookingType::cases(), 'value'))],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'location_type' => ['sometimes', 'required', Rule::in(array_column(LocationType::cases(), 'value'))],
            'location_address' => ['required_if:location_type,on_location', 'nullable', 'string', 'max:1000'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after:starts_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $booking = $this->route('booking');
            $userId = $this->input('assigned_user_id', $booking->assigned_user_id);

            if (! $userId || $validator->errors()->has('starts_at') || $validator->errors()->has('ends_at')) {
                return;
            }

            $startsAt = Carbon::parse($this->input('starts_at', $booking->starts_at));
            $endsAt = Carbon::parse($this->input('ends_at', $booking->ends_at));

            $hasConflict = app(BookingRepositoryInterface::class)->hasConflict($userId, $startsAt, $endsAt, $booking->id);

            if ($hasConflict) {
                $validator->errors()->add('assigned_user_id', 'This staff member already has a booking during that time.');
            }
        });
    }
}
