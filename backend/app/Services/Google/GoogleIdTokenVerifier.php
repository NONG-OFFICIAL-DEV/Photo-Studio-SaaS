<?php

namespace App\Services\Google;

use App\DTO\GoogleUserPayload;
use App\Exceptions\InvalidGoogleTokenException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifies a Google Identity Services ID token server-side, without pulling
 * in the full google/apiclient — just signature verification against
 * Google's own published JWKS plus the handful of claims that matter for
 * "is this really Google vouching for this email, for OUR app".
 */
class GoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    protected const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    protected const ALLOWED_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function verify(string $idToken): GoogleUserPayload
    {
        try {
            $keys = JWK::parseKeySet($this->jwks());
            $payload = JWT::decode($idToken, $keys);
        } catch (Throwable) {
            throw new InvalidGoogleTokenException;
        }

        $clientId = config('services.google.client_id');

        if (! $clientId || ($payload->aud ?? null) !== $clientId) {
            throw new InvalidGoogleTokenException;
        }

        if (! in_array($payload->iss ?? null, self::ALLOWED_ISSUERS, true)) {
            throw new InvalidGoogleTokenException;
        }

        if (empty($payload->sub) || empty($payload->email) || ! ($payload->email_verified ?? false)) {
            throw new InvalidGoogleTokenException;
        }

        return new GoogleUserPayload(
            sub: $payload->sub,
            email: $payload->email,
            emailVerified: true,
            name: $payload->name ?? null,
        );
    }

    /**
     * Google rotates its signing keys periodically but publishes a
     * Cache-Control max-age on this endpoint — caching for an hour keeps
     * every login from re-fetching it while still picking up rotations
     * promptly (Google keeps old keys published for a while after rotation).
     */
    protected function jwks(): array
    {
        return Cache::remember('google_jwks', now()->addHour(), function () {
            return Http::timeout(10)->get(self::JWKS_URL)->throw()->json();
        });
    }
}
