<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_phone
{
    /**
     * Normalizes a Brazilian mobile identity for matching only.
     *
     * The ten-digit form is accepted as a legacy mobile representation and
     * normalized by inserting the ninth digit. It is never treated as proof
     * of identity; callers must still handle unique and ambiguous matches.
     */
    public function normalizeIdentity($value)
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        if (substr($digits, 0, 2) === '55') {
            $brDigits = substr($digits, 2);
            $ddd = (int) substr($brDigits, 0, 2);
            if ($ddd >= 11 && $ddd <= 99) {
                if (strlen($brDigits) === 11 && $brDigits[2] === '9') {
                    return $digits;
                }
                if (strlen($brDigits) === 10 && preg_match('/[6-9]/', $brDigits[2])) {
                    return '55' . substr($brDigits, 0, 2) . '9' . substr($brDigits, 2);
                }
            }
            return $digits;
        }

        $ddd = (int) substr($digits, 0, 2);
        if ($ddd >= 11 && $ddd <= 99) {
            if (strlen($digits) === 11 && $digits[2] === '9') {
                return '55' . $digits;
            }
            if (strlen($digits) === 10 && preg_match('/[6-9]/', $digits[2])) {
                return '55' . substr($digits, 0, 2) . '9' . substr($digits, 2);
            }
        }

        return $digits;
    }

    public function normalizeWhatsApp($value)
    {
        $identity = $this->normalizeIdentity($value);
        if ($identity === null) {
            return null;
        }

        $length = strlen(preg_replace('/\D+/', '', (string) $value));
        return $length >= 8 && $length <= 15 ? $identity : null;
    }
}
