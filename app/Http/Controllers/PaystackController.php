<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaystackController extends Controller
{
    public function webhook(Request $req)
{
    // Validate signature
    $signature = $req->header('x-paystack-signature');
    $secret = env('PAYSTACK_WEBHOOK_SECRET');
    $payload = $req->getContent();
    $computed = hash_hmac('sha512', $payload, $secret);
    if (!hash_equals($computed, $signature)) {
        return response()->json(['status'=>false,'message'=>'Invalid signature'], 401);
    }

    $data = $req->json()->all();
    // Paystack sends $data['event'] and $data['data'] payload
    if (($data['event'] ?? '') !== 'charge.success' && ($data['event'] ?? '') !== 'charge.success') {
        // handle other events if necessary
        return response()->json(['status'=>true]);
    }

    $psData = $data['data'];
    $reference = $psData['reference'];
    $amount = $psData['amount']; // in kobo

    // Find the pending transaction by reference
    $txn = Transaction::where('reference', $reference)->first();

    if (!$txn) {
        // If you don't have a local transaction with that reference, optionally create one or log and ignore
        return response()->json(['status'=>false,'message'=>'Transaction not found'], 404);
    }

    // Idempotency: if already success, do nothing
    if ($txn->status === 'success') {
        return response()->json(['status'=>true]);
    }

    if ($psData['status'] === 'success') {
        // credit wallet atomically
        try {
            $wallet = $txn->wallet()->lockForUpdate()->first(); // lock row
            DB::beginTransaction();

            // double-check not already credited and amount match
            if ($txn->status !== 'success') {
                $wallet->increment('balance', $amount);
                $txn->update(['status'=>'success','meta'=>array_merge($txn->meta?:[], $psData)]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // log error
            Log::error('Webhook processing failed: '.$e->getMessage(), ['reference'=>$reference]);
            return response()->json(['status'=>false], 500);
        }
    } else {
        // mark failed
        $txn->update(['status'=>'failed','meta'=>array_merge($txn->meta?:[], $psData)]);
    }

    return response()->json(['status'=>true]);
}

}
