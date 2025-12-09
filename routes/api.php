<?php

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// Google OAuth routes
Route::get('auth/google', [AuthController::class,'redirectToGoogle']);
Route::get('auth/google/callback', [AuthController::class,'handleGoogleCallback']);

// Paystack webhook (public)
Route::post('wallet/paystack/webhook', [PaystackController::class,'webhook']);

// endpoints protected with JWT or API key
Route::middleware('api_or_key')->group(function () {
    Route::post('wallet/deposit', [WalletController::class,'initDeposit'])->middleware('api_or_key:deposit');
    Route::get('wallet/deposit/{reference}/status', [WalletController::class,'depositStatus'])->middleware('api_or_key:read');
    Route::get('wallet/balance', [WalletController::class,'balance'])->middleware('api_or_key:read');
    Route::post('wallet/transfer', [WalletController::class,'transfer'])->middleware('api_or_key:transfer');
    Route::get('wallet/transactions', [WalletController::class,'transactions'])->middleware('api_or_key:read');
});

// API key management - only via JWT user (no API key management via API key)
Route::middleware(['auth:api'])->group(function() {
    Route::post('keys/create', [ApiKeyController::class,'create']);
    Route::post('keys/rollover', [ApiKeyController::class,'rollover']);
    Route::post('keys/revoke', [ApiKeyController::class,'revoke']);
});
