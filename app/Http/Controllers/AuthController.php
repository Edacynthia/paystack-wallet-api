<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function redirectToGoogle()
{
    return Socialite::driver('google')->stateless()->redirect();
}

public function handleGoogleCallback()
{
    $googleUser = Socialite::driver('google')->stateless()->user();

    $user = User::firstOrCreate(
        ['email' => $googleUser->getEmail()],
        ['name' => $googleUser->getName(), 'google_id' => $googleUser->getId()]
    );

    // ensure wallet exists
    if (!$user->wallet) {
        $user->wallet()->create(['balance'=>0]);
    }

    $token = JWTAuth::fromUser($user);
    return response()->json(['token' => $token, 'token_type' => 'bearer']);
}
}
