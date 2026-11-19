<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

class Money
{
    /** Convert a decimal USD-per-million rate to nanodollars per token. */
    public static function rateToNanos(string $rate): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $rate, $matches)) {
            throw new InvalidArgumentException('Rate must be a non-negative decimal with at most three decimal places.');
        }

        return (int) $matches[1] * 1000 + (int) str_pad($matches[2] ?? '', 3, '0');
    }

    public static function charge(int $costNanos, int $markupPercent = 25): int
    {
        if ($costNanos < 0 || $markupPercent < 0) {
            throw new InvalidArgumentException('Cost and markup must be non-negative.');
        }

        // Divide first so the multiplication does not unnecessarily enlarge the cost.
        $markup = intdiv($costNanos, 100) * $markupPercent;
        $remainder = ($costNanos % 100) * $markupPercent;

        return $costNanos + $markup + intdiv($remainder + 99, 100);
    }

    public static function format(int $nanos): string
    {
        if ($nanos < 0) {
            throw new InvalidArgumentException('Amount must be non-negative.');
        }

        return intdiv($nanos, 1_000_000_000).'.'.str_pad((string) ($nanos % 1_000_000_000), 9, '0', STR_PAD_LEFT);
    }
}
