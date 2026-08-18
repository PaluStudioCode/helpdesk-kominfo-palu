<?php

namespace App\Services;

class PhoneNormalizer
{
    /**
     * Normalize Indonesian phone number from 08xx / +628xx to 628xx.
     */
    public static function normalize(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-digit characters (+, -, space, parentheses, etc)
        $cleaned = preg_replace('/\D/', '', $phone);

        if (empty($cleaned)) {
            return null;
        }

        // 08xx -> 628xx
        if (str_starts_with($cleaned, '0')) {
            return '62' . substr($cleaned, 1);
        }

        // 8xx -> 628xx (if someone typed without leading 0 or 62)
        if (str_starts_with($cleaned, '8')) {
            return '62' . $cleaned;
        }

        // 628xx -> 628xx (already normalized)
        return $cleaned;
    }
}
