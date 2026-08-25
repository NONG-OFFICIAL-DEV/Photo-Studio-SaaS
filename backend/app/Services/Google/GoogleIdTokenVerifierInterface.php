<?php

namespace App\Services\Google;

use App\DTO\GoogleUserPayload;
use App\Exceptions\InvalidGoogleTokenException;

interface GoogleIdTokenVerifierInterface
{
    /**
     * @throws InvalidGoogleTokenException
     */
    public function verify(string $idToken): GoogleUserPayload;
}
