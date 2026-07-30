<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidCredentialsException extends Exception
{
    use ApiResponse;

    public function __construct(
        string $message = 'These credentials do not match our records.',
        protected string $errorCode = 'INVALID_CREDENTIALS',
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return $this->error($this->getMessage(), 401, [], $this->errorCode);
    }
}
