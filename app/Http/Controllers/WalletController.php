<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function initDeposit(Request $req)
{
    $req->validate(['amount'=>'required|integer|min:1']);

    $user = $req->user();
    $wallet = $user->wallet ?? $user->wallet()->create(['balance'=>0]);

    $amountKobo = $req->amount * 100; // store and send lowest currency unit

    // create pending transaction (idempotency: reference must be unique)
    $reference = 'paystack_' . Str::random(12);
    $txn = $wallet->transactions()->create([
        'reference' => $reference,
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => $amountKobo,
        'meta' => ['initiated_by' => $user->id],
    ]);

    // call Paystack initialize
    $resp = Http::withToken(config('services.paystack.secret') ?? env('PAYSTACK_SECRET'))
        ->post(config('services.paystack.base') ?? env('PAYSTACK_BASE') . '/transaction/initialize', [
            'amount' => $amountKobo,
            'email' => $user->email,
            'reference' => $reference,
            'callback_url' => env('APP_URL') . '/api/wallet/paystack/webhook'
        ]);

    $body = $resp->json();

    if (!($resp->ok() && $body['status'] === true)) {
        // update transaction as failed
        $txn->update(['status'=>'failed','meta'=>array_merge($txn->meta?:[], ['paystack_error'=>$body])]);
        return response()->json(['message'=>'Paystack initialize failed'], 500);
    }

    return response()->json([
        'reference' => $reference,
        'authorization_url' => $body['data']['authorization_url'],
    ]);
}

public function depositStatus($reference)
{
    $txn = Transaction::where('reference',$reference)->firstOrFail();
    return response()->json(['reference'=>$txn->reference,'status'=>$txn->status,'amount'=>$txn->amount]);
}

public function balance(Request $req)
{
    $wallet = $req->user()->wallet;
    return response()->json(['balance' => $wallet->balance]); // return as integer in kobo/cents
}

public function transactions(Request $req)
{
    $txns = $req->user()->wallet->transactions()->latest()->take(50)->get(['type','amount','status','reference','created_at']);
    return response()->json($txns);
}


public function transfer(Request $req)
{
    $req->validate(['wallet_number'=>'required|string','amount'=>'required|integer|min:1']);
    $sender = $req->user();
    $senderWallet = $sender->wallet;
    $amountKobo = $req->amount * 100;

    // find recipient by wallet number - adapt to your lookup scheme
    $recipientUser = User::where('wallet_number', $req->wallet_number)->first();
    if (!$recipientUser) return response()->json(['message'=>'Recipient not found'],404);

    $recipientWallet = $recipientUser->wallet ?? $recipientUser->wallet()->create(['balance'=>0]);

    // atomic transfer
    DB::beginTransaction();
    try {
        // debit sender
        if ($senderWallet->balance < $amountKobo) {
            DB::rollBack();
            return response()->json(['message'=>'Insufficient balance'], 422);
        }

        $ref = 'transfer_' . Str::random(12);

        $senderTxn = $senderWallet->transactions()->create([
            'reference' => $ref,
            'type' => 'transfer',
            'status' => 'success',
            'amount' => -1 * $amountKobo, // optional: negative to indicate debit OR keep positive and type indicates debit
            'meta' => ['to_user' => $recipientUser->id],
        ]);
        $senderWallet->decrement('balance', $amountKobo);

        $recipientTxn = $recipientWallet->transactions()->create([
            'reference' => $ref,
            'type' => 'transfer',
            'status' => 'success',
            'amount' => $amountKobo,
            'meta' => ['from_user' => $sender->id],
        ]);
        $recipientWallet->increment('balance', $amountKobo);

        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message'=>'Transfer failed','error'=>$e->getMessage()], 500);
    }

    return response()->json(['status'=>'success','message'=>'Transfer completed']);
}



}
