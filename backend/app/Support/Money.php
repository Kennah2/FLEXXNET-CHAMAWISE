<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is always stored and summed as an integer count of minor currency units (cents for
 * KES) — never a PHP float. This container (and potentially the target shared-hosting
 * environment, per docs/SHARED_HOSTING_DEPLOYMENT.md) cannot be assumed to have the bcmath
 * extension, so integer minor units are used instead of bcmath-backed decimal strings: plain
 * integer arithmetic is exact and has no extension dependency (Part 12 / Part 28 of the
 * specification — never use floating-point arithmetic for money).
 */
final class Money
{
    /**
     * Parses a user-entered amount such as "100", "100.5" or "1,250.00" into integer cents.
     * Never casts through float. Throws on anything that isn't a plain decimal amount.
     */
    public static function parseToCents(string $input): int
    {
        $normalized = trim(str_replace(',', '', $input));

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException("Invalid money amount: \"{$input}\".");
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole) * 100 + (int) $fraction;
    }

    public static function format(int $cents, string $currency = 'KES'): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%s %s.%02d', $sign, $currency, intdiv($absolute, 100), $absolute % 100);
    }
}
