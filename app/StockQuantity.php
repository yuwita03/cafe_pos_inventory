<?php

namespace App;

use InvalidArgumentException;

final class StockQuantity
{
    public const int MAX_MILLI = 999999999999;

    public static function milli(string|int|float $quantity): int
    {
        $value = (string) $quantity;
        if (! preg_match('/^-?\d{1,9}(?:\.\d{1,3})?$/D', $value)) {
            throw new InvalidArgumentException('Invalid stock quantity.');
        }
        $negative = str_starts_with($value, '-');
        $parts = explode('.', ltrim($value, '-'));

        return ((int) $parts[0] * 1000 + (int) str_pad($parts[1] ?? '', 3, '0')) * ($negative ? -1 : 1);
    }

    public static function decimal(int $milli): string
    {
        return ($milli < 0 ? '-' : '').intdiv(abs($milli), 1000).'.'.str_pad((string) (abs($milli) % 1000), 3, '0', STR_PAD_LEFT);
    }
}
