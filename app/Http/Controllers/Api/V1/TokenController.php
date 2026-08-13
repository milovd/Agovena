<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Agovena\Api\ApiError;
use App\Agovena\Audit\AuditLogger;
use App\Http\Resources\Api\V1\AccountResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

final class TokenController
{
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'name' => ['required', 'string', 'max:80'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if ($user === null || ! Hash::check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('api.auth.invalid')],
            ]);
        }

        if ($user->anonymized_at !== null) {
            return ApiError::json('unauthorized', __('api.auth.unavailable'), 403);
        }

        $new = $user->createToken($data['name'], ['*']);
        $audit->log('api_token.created', $user, [
            'token_name' => $data['name'],
            'token_id' => $new->accessToken->id,
        ]);

        return response()->json([
            'token' => $new->plainTextToken,
            'token_type' => 'Bearer',
            'name' => $data['name'],
        ], 201);
    }

    public function destroy(Request $request, AuditLogger $audit): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $audit->log('api_token.revoked', $request->user(), [
                'token_name' => $token->name,
                'token_id' => $token->id,
            ]);
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): AccountResource
    {
        return new AccountResource(authenticated_customer());
    }

    public function updateMe(Request $request): AccountResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user->name = $data['name'];
        $user->save();

        return new AccountResource(authenticated_customer());
    }
}
