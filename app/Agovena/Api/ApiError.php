<?php

declare(strict_types=1);

namespace App\Agovena\Api;

use Illuminate\Http\JsonResponse;

final class ApiError
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function json(string $code, string $message, int $status, array $errors = []): JsonResponse
    {
        $payload = [
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
