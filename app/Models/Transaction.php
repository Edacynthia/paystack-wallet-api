<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $casts = [
        'meta' => 'array'
    ];
    protected $fillable = [
        'reference',
        'wallet_id', 
        'type', 
        'status', 
        'amount', 
        'meta', 
        'related_id'
    ];
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
