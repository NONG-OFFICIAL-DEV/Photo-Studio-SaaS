<?php

namespace App\Services\Google;

use App\Exceptions\InvalidGoogleTokenException;

interface GoogleAuthorizationCodeExchangerInterface
{
    /**
     * Exchanges a one-time authorization code (from the frontend's custom
     * button, Google's OAuth2 code-client popup flow) for an ID token.
     *
     * @throws InvalidGoogleTokenException
     */
    public function exchange(string $code): string;
}
