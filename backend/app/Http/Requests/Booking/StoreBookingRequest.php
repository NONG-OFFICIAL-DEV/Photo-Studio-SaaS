<?php

namespace App\Http\Requests\Booking;

use App\Enums\BookingType;
use App\Enums\LocationType;
use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Booking::class);
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required', 'uuid',
                Rule::exists('customers', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'assigned_user_id' => [
                'nullable', 'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'branch_id' => [
                'nullable', 'uuid',
                Rule::exists('branches', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'type' => ['required', Rule::in(array_column(BookingType::cases(), 'value'))],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'location_type' => ['required', Rule::in(array_column(LocationType::cases(), 'value'))],
            'location_address' => ['required_if:location_type,on_location', 'nullable', 'string', 'max:1000'],
            // Create-time only — editing an existing booking (UpdateBookingRequest)
            // deliberately allows past dates, since correcting/backfilling an
            // already-recorded booking is a legitimate edit, not a new booking.
            'starts_at' => ['required', 'date', 'after_or_equal:today'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $userId = $this->input('assigned_user_id');

            if (! $userId || $validator->errors()->has('starts_at') || $validator->errors()->has('ends_at')) {
                return;
            }

            $hasConflict = app(BookingRepositoryInterface::class)->hasConflict(
                $userId,
                Carbon::parse($this->input('starts_at')),
                Carbon::parse($this->input('ends_at')),
            );

            if ($hasConflict) {
                $validator->errors()->add('assigned_user_id', 'This staff member already has a booking during that time.');
            }
        });
    }
}
