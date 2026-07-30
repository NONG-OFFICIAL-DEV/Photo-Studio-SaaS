<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Guarantees every API response follows the platform-wide envelope:
 * { "success": bool, "message": string, "code": string|null, "data": mixed, "meta": array }
 *
 * `message` is always English (kept for logs/tests/API consumers without a
 * locale concept). `code` is a stable machine-readable identifier the
 * frontend maps to a translated string (see frontend/src/utils/apiMessages.js)
 * — omit it only for messages a user never sees untranslated. `params`
 * carries any values baked into the English message (a plan name, an
 * amount, ...) so the frontend's translated string can interpolate them too.
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = '', int $status = 200, array $meta = [], ?string $code = null, array $params = []): JsonResponse
    {
        $payload = $data instanceof JsonResource || $data instanceof ResourceCollection
            ? $data->response()->getData(true)
            : ['data' => $data];

        return response()->json([
            'success' => true,
            'message' => $message,
            'code' => $code,
            'params' => $params,
            'data' => $payload['data'] ?? $data,
            'meta' => array_merge($payload['meta'] ?? [], $meta),
        ], $status);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully.', ?string $code = 'CREATED', array $params = []): JsonResponse
    {
        return $this->success($data, $message, 201, [], $code, $params);
    }

    protected function noContent(string $message = 'Deleted successfully.', ?string $code = 'DELETED'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'code' => $code,
            'params' => [],
            'data' => null,
            'meta' => [],
        ], 200);
    }

    protected function error(string $message = 'Something went wrong.', int $status = 400, array $errors = [], ?string $code = null, array $params = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'params' => $params,
            'data' => null,
            'meta' => $errors ? ['errors' => $errors] : [],
        ], $status);
    }
}
