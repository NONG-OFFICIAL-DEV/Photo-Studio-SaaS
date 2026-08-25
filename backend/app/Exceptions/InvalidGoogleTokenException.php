<?php

namespace App\Exceptions;

/**
 * Thrown whenever a Google ID token fails signature/claim verification
 * (expired, wrong audience, unverified email, malformed, ...) — deliberately
 * generic to the client (never reveals which specific check failed).
 */
class InvalidGoogleTokenException extends ApiException
{
    public function __construct(string $message = 'This Google sign-in could not be verified. Please try again.')
    {
        parent::__construct(401, $message, 'INVALID_GOOGLE_TOKEN');
    }
}
