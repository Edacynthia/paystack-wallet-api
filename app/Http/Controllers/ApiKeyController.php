<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;         
use App\Helpers\ExpiryHelper;

class ApiKeyController extends Controller
{
    public function create(Request $req)
{
    $req->validate([
        'name'=>'required|string',
        'permissions'=>'required|array|min:1',
        'expiry'=>'required|string',
    ]);

    $user = $req->user(); // JWT user; keys API endpoints must require JWT

    // enforce max 5 active keys
    $activeCount = $user->apiKeys()->whereNull('revoked_at')->where('expires_at','>',now())->count();
    if ($activeCount >= 5) {
        return response()->json(['message'=>'Maximum 5 active API keys allowed'], 422);
    }

    $expiresAt = ExpiryHelper::expiryToDatetime($req->expiry);

    // generate plaintext key (show once)
    $plain = 'sk_' . Str::random(40);
    $hash = hash('sha256', $plain);

    $apiKey = $user->apiKeys()->create([
        'name'=>$req->name,
        'key_hash'=>$hash,
        'permissions'=>$req->permissions,
        'expires_at'=>$expiresAt,
    ]);

    return response()->json([
        'api_key'=>$plain,
        'expires_at'=>$apiKey->expires_at->toIso8601String(),
        'id'=>$apiKey->id,
    ]);
}

public function revoke(Request $req)
{
    $req->validate(['key_id'=>'required|integer']);
    $apiKey = $req->user()->apiKeys()->findOrFail($req->key_id);
    $apiKey->update(['revoked_at' => now()]);
    return response()->json(['status'=>true]);
}

public function rollover(Request $req)
{
    $req->validate(['expired_key_id'=>'required|integer','expiry'=>'required|string']);
    $user = $req->user();
    $old = $user->apiKeys()->findOrFail($req->expired_key_id);

    if (!$old->isExpired()) {
        return response()->json(['message'=>'Key is not expired'], 422);
    }

    // enforce 5 active keys
    $activeCount = $user->apiKeys()->whereNull('revoked_at')->where('expires_at','>',now())->count();
    if ($activeCount >= 5) {
        return response()->json(['message'=>'Maximum 5 active API keys allowed'], 422);
    }

   $expiresAt = ExpiryHelper::expiryToDatetime($req->expiry);

    $plain = 'sk_' . Str::random(40);
    $hash = hash('sha256', $plain);

    $new = $user->apiKeys()->create([
        'name' => $old->name . ' (rollover)',
        'key_hash' => $hash,
        'permissions' => $old->permissions,
        'expires_at' => $expiresAt,
    ]);

    return response()->json(['api_key'=>$plain,'expires_at'=>$new->expires_at->toIso8601String(),'id'=>$new->id]);
}
}
