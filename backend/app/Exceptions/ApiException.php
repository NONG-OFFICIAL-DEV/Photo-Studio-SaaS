<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown by services for business-logic errors (plan limits, invalid state
 * transitions, ...) instead of a bare HttpException, so the response can
 * carry a stable machine-readable `code` — the frontend translates that
 * code to the user's locale (see frontend/src/utils/apiMessages.js) instead
 * of displaying the English `message` verbatim.
 *
 * `params` carries any values baked into the English message (a plan name,
 * an amount, ...) so the frontend's translated string can interpolate them
 * too, e.g. code `PLAN_HAS_NOTHING_TO_RENEW` + params `{plan: 'Starter'}`.
 */
class ApiException extends HttpException
{
    public function __construct(int $statusCode, string $message, public readonly string $errorCode, public readonly array $params = [])
    {
        parent::__construct($statusCode, $message);
    }
}
