<?php

namespace App\DTO;

use App\Http\Requests\Auth\GoogleRegisterRequest;
use Illuminate\Support\Str;

/**
 * Studio-side fields needed to provision a brand-new tenant when a Google
 * sign-in has no matching account yet. Owner name/email/password aren't
 * here — those come from the verified GoogleUserPayload instead.
 */
final readonly class GoogleRegisterData
{
    public function __construct(
        public string $studioName,
        public ?string $slug,
        public ?string $phone,
        public ?string $planCode,
        public ?string $billingCycle,
    ) {}

    public static function fromRequest(GoogleRegisterRequest $request): self
    {
        $data = $request->validated();

        return new self(
            studioName: $data['studio_name'],
            slug: $data['slug'] ?? Str::slug($data['studio_name']).'-'.Str::lower(Str::random(5)),
            phone: $data['phone'] ?? null,
            planCode: $data['plan_code'] ?? null,
            billingCycle: $data['billing_cycle'] ?? null,
        );
    }
}
