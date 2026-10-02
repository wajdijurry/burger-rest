<?php

namespace App\Http;

use Illuminate\Http\JsonResponse;

/**
 * Builds the consistent API error envelope used by the exception renderer
 * registered in bootstrap/app.php: { "error": { code, message, fields? } }.
 */
final class ApiError
{
    public static function response(string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return response()->json(['error' => $error], $status);
    }
}
