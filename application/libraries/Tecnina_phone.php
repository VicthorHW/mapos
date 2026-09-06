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
    public function normalizeBrazilianIdentity($value)
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        if (substr($digits, 0, 2) === '55') {
            $digits = substr($digits, 2);
        }

        $ddd = (int) substr($digits, 0, 2);
        if ($ddd < 11 || $ddd > 99) {
            return null;
        }

        if (strlen($digits) === 11 && $digits[2] === '9') {
            return '55' . $digits;
        }

        // Legacy Brazilian mobile numbers had eight subscriber digits. Fixed
        // lines (which begin with 2-5) are deliberately not matched here.
        if (strlen($digits) === 10 && preg_match('/[6-9]/', $digits[2])) {
            return '55' . substr($digits, 0, 2) . '9' . substr($digits, 2);
        }

        return null;
    }

    public function normalizeBrazilianWhatsApp($value)
    {
        $identity = $this->normalizeBrazilianIdentity($value);
        if ($identity === null) {
            return null;
        }

        $digits = substr($identity, 2);

        return strlen(preg_replace('/\D+/', '', (string) $value)) === 13 ||
            strlen(preg_replace('/\D+/', '', (string) $value)) === 11
            ? $identity
            : null;
    }
}
