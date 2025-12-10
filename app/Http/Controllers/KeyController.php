<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class KeyController extends Controller
{
    // Protect this route with the JWT user token
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'expires_in_days' => 'nullable|integer|min:1',
        ]);

        $expiration = $request->expires_in_days
            ? now()->addDays($request->expires_in_days)
            : null;

        // Generate the API key (Sanctum token) with a custom ability
        $token = $request->user()->createToken(
            $request->name,
            ['service:access'],
            // Supports expiration
            $expiration
        );

        return response()->json([
            "success" => true,
            'id' => $token->accessToken->id,
            // IMPORTANT: Display once!
            'plain_text_token' => $token->plainTextToken,
            'expires_at' => $expiration,
        ]);
    }

    // Revoke a specific API key
    public function revoke(Request $request, $tokenId)
    {

        // Find the token belonging to the authenticated user
        $result = $request->user()->tokens()
            ->where('id', $tokenId)
            ->delete(); // Revokes the token instantly

        if (!$result) {
            return response()->json([
                "success" => false,
                'message' => 'API Key not found or does not belong to the user'
            ]);
        }

        return response()->json([
            "success" => true,
            'message' => 'API Key revoked successfully'
        ]);
    }

    public function rollover(Request $request)
{
    $request->validate([
        'expired_key_id' => 'required|string',
        'expiry' => 'required|string', // e.g., "1M", "30d", etc.
    ]);

    $token = PersonalAccessToken::findToken($request->expired_key_id);

    if (!$token) {
        return response()->json([
            'success' => false,
            'message' => 'Token not found',
        ], 404);
    }

    // Optionally revoke old token
    $token->delete();

    // Create new token with same abilities
    $user = $token->tokenable;
    $abilities = $token->abilities;

    // Set expiry if you want custom expiration
    $newToken = $user->createToken('RolloverToken', $abilities);

    return response()->json([
        'success' => true,
        'message' => 'Token rolled over successfully',
        'data' => [
            'new_token' => $newToken->plainTextToken,
        ],
    ]);
}


    public function createKey(Request $request)
    {
        $user = $request->user();
        // 1. Max 5 active keys check
        if ($user->tokens()->whereNull('expires_at')->count() >= 5) {
            return response()->json(['error' => 'Maximum 5 active API keys allowed.'], 403);
        }

        // 2. Permission and Expiry Parsing (simplified)
        $permissions = $request->input('permissions', ['read']);
        $expiryInput = $request->input('expiry', '1Y'); // e.g., 1D, 1M, 1Y

        $expires_at = match ($expiryInput) {
            '1H' => now()->addHour(),
            '1D' => now()->addDay(),
            '1M' => now()->addMonth(),
            '1Y' => now()->addYear(),
            default => null, // Default to no expiry or handle error
        };

        // 3. Create Token (Sanctum)
        $tokenResult = $user->createToken(
            $request->name,
            $permissions,
            $expires_at
        );

        return response()->json([
            'api_key' => $tokenResult->plainTextToken,
            'expires_at' => $expires_at,
        ]);
    }
}