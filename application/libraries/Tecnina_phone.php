<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_phone
{
    public function normalizeBrazilianWhatsApp($value)
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        if (substr($digits, 0, 2) === '55') {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) !== 11 || $digits[2] !== '9') {
            return null;
        }

        $ddd = (int) substr($digits, 0, 2);
        if ($ddd < 11 || $ddd > 99) {
            return null;
        }

        return '55' . $digits;
    }
}
