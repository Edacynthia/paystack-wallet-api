<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiOrKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
     public function handle($request, Closure $next, $permission = null)
    {
        // 1. Try JWT (tymon)
        if ($request->bearerToken()) {
            try {
                $user = auth('api')->user(); // configure guard to use jwt driver
                if ($user) {
                    $request->setUserResolver(fn()=> $user);
                    return $next($request);
                }
            } catch (\Exception $e) {
                // ignore and fallback to API key
            }
        }

        // 2. Try API key header x-api-key
        $plain = $request->header('x-api-key');
        if (!$plain) {
            return response()->json(['message'=>'Unauthorized - no credentials'], 401);
        }
        $hash = hash('sha256', $plain);
        $apiKey = \App\Models\ApiKey::where('key_hash', $hash)->first();

        if (!$apiKey) {
            return response()->json(['message'=>'Invalid API key'], 401);
        }
        if ($apiKey->isExpired()) {
            return response()->json(['message'=>'API key expired'], 401);
        }
        if ($apiKey->isRevoked()) {
            return response()->json(['message'=>'API key revoked'], 401);
        }

        // permission check (if middleware receives permission param)
        if ($permission) {
            $perms = $apiKey->permissions ?: [];
            if (!in_array($permission, $perms)) {
                return response()->json(['message'=>'API key missing permission: '.$permission], 403);
            }
        }

        // attach impersonated user to request (so controllers can use $request->user())
        $request->setUserResolver(fn()=> $apiKey->user);

        // attach api_key context
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}

