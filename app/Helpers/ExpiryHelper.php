<?php

namespace App\Helpers;

use Carbon\Carbon;

class ExpiryHelper
{
    public static function expiryToDatetime(string $expiry): Carbon
    {
        $expiry = strtoupper($expiry);

        return match ($expiry) {
            '1H' => now()->addHour(),
            '1D' => now()->addDay(),
            '1M' => now()->addMonth(),
            '1Y' => now()->addYear(),
            default => throw new \InvalidArgumentException("Invalid expiry format. Use 1H,1D,1M,1Y"),
        };
    }
}
