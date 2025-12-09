<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    protected $fillable = ['user_id','balance'];

    public function user() { return $this->belongsTo(User::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }

    // Atomic credit
    public function credit(int $amount, string $reference, array $meta = [], string $type='deposit')
    {
        return DB::transaction(function () use ($amount, $reference, $meta, $type) {
            $this->increment('balance', $amount);
            return $this->transactions()->create([
                'reference' => $reference,
                'type' => $type,
                'status' => 'success',
                'amount' => $amount,
                'meta' => $meta,
            ]);
        });
    }

    // Atomic debit
    public function debit(int $amount, string $reference, array $meta = [], string $type='transfer')
    {
        return DB::transaction(function () use ($amount, $reference, $meta, $type) {
            $this->refresh();
            if ($this->balance < $amount) {
                throw new \Exception('Insufficient balance');
            }
            $this->decrement('balance', $amount);
            return $this->transactions()->create([
                'reference' => $reference,
                'type' => $type,
                'status' => 'success',
                'amount' => $amount,
                'meta' => $meta,
            ]);
        });
    }
}
