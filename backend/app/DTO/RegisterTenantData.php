<?php

namespace App\DTO;

use App\Http\Requests\Auth\RegisterTenantRequest;
use Illuminate\Support\Str;

final readonly class RegisterTenantData
{
    public function __construct(
        public string $studioName,
        public string $slug,
        public string $ownerName,
        public string $email,
        public ?string $phone,
        public string $password,
        public ?string $planCode,
    ) {
    }

    public static function fromRequest(RegisterTenantRequest $request): self
    {
        $data = $request->validated();

        return new self(
            studioName: $data['studio_name'],
            slug: $data['slug'] ?? Str::slug($data['studio_name']).'-'.Str::lower(Str::random(5)),
            ownerName: $data['owner_name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            password: $data['password'],
            planCode: $data['plan_code'] ?? null,
        );
    }
}
