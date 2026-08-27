<?php

namespace Tests\Support;

use App\Services\Google\GoogleAuthorizationCodeExchangerInterface;

/**
 * Swapped in for GoogleAuthorizationCodeExchangerInterface in tests so
 * nothing ever hits Google's real token endpoint. A transparent passthrough
 * — tests use the same string as both the "code" posted to the API and the
 * FakeGoogleIdTokenVerifier's lookup key, so existing test fixtures barely
 * needed to change when this exchange step was introduced.
 */
class FakeGoogleAuthorizationCodeExchanger implements GoogleAuthorizationCodeExchangerInterface
{
    public function exchange(string $code): string
    {
        return $code;
    }
}
