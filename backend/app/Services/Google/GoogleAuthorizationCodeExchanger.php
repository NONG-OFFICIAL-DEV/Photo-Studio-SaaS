<?php

namespace App\Services\Google;

use App\Exceptions\InvalidGoogleTokenException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Exchanges a one-time authorization code (from the frontend's custom
 * button, Google's OAuth2 code-client popup flow) for an ID token via
 * Google's token endpoint — a real server-to-server call using
 * client_secret, which never reaches the frontend. The returned ID token
 * is then verified exactly as before, via GoogleIdTokenVerifier.
 */
class GoogleAuthorizationCodeExchanger implements GoogleAuthorizationCodeExchangerInterface
{
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function exchange(string $code): string
    {
        try {
            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                // Google's documented value for a popup-mode code client —
                // no real redirect URI is registered/needed for this flow.
                'redirect_uri' => 'postmessage',
                'grant_type' => 'authorization_code',
            ]);
        } catch (Throwable) {
            throw new InvalidGoogleTokenException;
        }

        $idToken = $response->json('id_token');

        if (! $response->successful() || ! $idToken) {
            throw new InvalidGoogleTokenException;
        }

        return $idToken;
    }
}
