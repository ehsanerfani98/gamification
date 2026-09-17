<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * قرارداد ساختار پاسخ — فصل ۸-۱ سند معماری:
 * موفق: { "data": ... } | خطا: { "error": { code, message, fields } }
 */
trait ApiResponse
{
    protected function ok(array|object|null $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    protected function created(array|object|null $data = null): JsonResponse
    {
        return $this->ok($data, 201);
    }

    protected function fail(string $code, string $message, int $status = 422, array $fields = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields,
            ],
        ], $status);
    }
}
