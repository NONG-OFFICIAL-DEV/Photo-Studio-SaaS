<?php

namespace App\DTO;

final readonly class GoogleUserPayload
{
    public function __construct(
        public string $sub,
        public string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {}
}
