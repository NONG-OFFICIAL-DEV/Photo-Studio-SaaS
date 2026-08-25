<?php

namespace Tests\Support;

use App\DTO\GoogleUserPayload;
use App\Exceptions\InvalidGoogleTokenException;
use App\Services\Google\GoogleIdTokenVerifierInterface;

/**
 * Swapped in for GoogleIdTokenVerifierInterface in tests so nothing ever
 * hits Google's real JWKS endpoint. Seed a fake "id_token" => claims
 * mapping via self::$claims; verify() enforces email_verified the same way
 * the real implementation does, so tests can exercise that rejection path
 * through the actual AuthController/AuthService, not just the verifier.
 */
class FakeGoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    /** @var array<string, array{sub: string, email: string, email_verified: bool, name?: string}> */
    public static array $claims = [];

    public function verify(string $idToken): GoogleUserPayload
    {
        $claim = self::$claims[$idToken] ?? null;

        if (! $claim || empty($claim['email_verified'])) {
            throw new InvalidGoogleTokenException;
        }

        return new GoogleUserPayload(
            sub: $claim['sub'],
            email: $claim['email'],
            emailVerified: true,
            name: $claim['name'] ?? null,
        );
    }
}
