<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Guarantees every API response follows the platform-wide envelope:
 * { "success": bool, "message": string, "data": mixed, "meta": array }
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = '', int $status = 200, array $meta = []): JsonResponse
    {
        $payload = $data instanceof JsonResource || $data instanceof ResourceCollection
            ? $data->response()->getData(true)
            : ['data' => $data];

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $payload['data'] ?? $data,
            'meta' => array_merge($payload['meta'] ?? [], $meta),
        ], $status);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully.'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent(string $message = 'Deleted successfully.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => null,
            'meta' => [],
        ], 200);
    }

    protected function error(string $message = 'Something went wrong.', int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => $errors ? ['errors' => $errors] : [],
        ], $status);
    }
}
