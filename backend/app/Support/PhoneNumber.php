<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Normalizes a Kenyan phone number into the 2547XXXXXXXX / 2541XXXXXXXX format Safaricom's
 * Daraja API requires for STK Push, regardless of how a member typed it in (07.., 01.., +254..,
 * 254.., or bare 9-digit).
 */
final class PhoneNumber
{
    public static function normalizeKenyan(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (preg_match('/^254[17]\d{8}$/', $digits)) {
            return $digits;
        }

        if (preg_match('/^0[17]\d{8}$/', $digits)) {
            return '254'.substr($digits, 1);
        }

        if (preg_match('/^[17]\d{8}$/', $digits)) {
            return '254'.$digits;
        }

        throw new InvalidArgumentException("Invalid Kenyan phone number: \"{$input}\".");
    }
}
