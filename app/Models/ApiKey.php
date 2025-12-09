<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $casts = [
        'permissions' => 'array', 
        'expires_at' => 'datetime', 
        'revoked_at' => 'datetime'
    ];
    protected $fillable = [
        'user_id',
        'name', 
        'key_hash', 
        'permissions', 
        'expires_at', 
        'revoked_at'
    ];

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }
    public function isRevoked()
    {
        return !is_null($this->revoked_at);
    }
    public function isActive()
    {
        return !$this->isExpired() && !$this->isRevoked();
    }
}
