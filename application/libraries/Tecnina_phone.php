<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_phone
{
    /**
     * Normalizes a canonical international identity.
     *
     * Rules:
     * - Strips non-digits and transport characters.
     * - Valid boundary: 8 to 15 digits (ITU-T E.164 boundary).
     * - If DDI == 55 (Brazil):
     *   - DDD must be between 11 and 99.
     *   - 11-digit mobile with 9th digit '9' is preserved (55XXXXXXXXXXX).
     *   - 10-digit legacy mobile ([6-9]) is normalized by inserting '9' (55XX9XXXXXXXX).
     *   - Fixed-line numbers (subscriber starting with 2-5) are rejected (returns null).
     *   - Other lengths or invalid Brazilian structures return null.
     * - If DDI != 55 (International):
     *   - Preserves exact international canonical digits.
     *   - NEVER infers Brazil merely based on length or prefix heuristics.
     * - Short values (< 8 digits) or exceeding 15 digits return null.
     */
    public function normalizeCanonicalIdentity($value)
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        if (substr($digits, 0, 2) === '55') {
            $brDigits = substr($digits, 2);
            if (strlen($brDigits) < 10) {
                return null;
            }
            $ddd = (int) substr($brDigits, 0, 2);
            if ($ddd < 11 || $ddd > 99) {
                return null;
            }

            if (strlen($brDigits) === 11 && $brDigits[2] === '9') {
                return $digits;
            }
            if (strlen($brDigits) === 10 && preg_match('/[6-9]/', $brDigits[2])) {
                return '55' . substr($brDigits, 0, 2) . '9' . substr($brDigits, 2);
            }

            return null;
        }

        // If without 55 it matches Brazilian local fixed line, reject as identity
        if (strlen($digits) === 10) {
            $ddd = (int) substr($digits, 0, 2);
            if ($ddd >= 11 && $ddd <= 99 && preg_match('/[2-5]/', $digits[2])) {
                return null;
            }
        }

        // Canonical international identity (DDI != 55)
        return $digits;
    }

    /**
     * Backward-compatible entry point for canonical international identity normalization.
     * Delegates strictly to normalizeCanonicalIdentity().
     */
    public function normalizeIdentity($value)
    {
        return $this->normalizeCanonicalIdentity($value);
    }

    /**
     * Generates canonical Brazilian aliases for local/legacy inputs without country code.
     *
     * Used ONLY when the input is known to be potentially local Brazilian input
     * (e.g. candidate phone numbers stored in MapOS without country code).
     */
    public function brazilianIdentityAliases($value)
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return [];
        }

        $brDigits = substr($digits, 0, 2) === '55' ? substr($digits, 2) : $digits;
        $len = strlen($brDigits);

        if ($len === 10) {
            $ddd = (int) substr($brDigits, 0, 2);
            if ($ddd >= 11 && $ddd <= 99 && preg_match('/[6-9]/', $brDigits[2])) {
                return ['55' . substr($brDigits, 0, 2) . '9' . substr($brDigits, 2)];
            }
            return [];
        }

        if ($len === 11) {
            $ddd = (int) substr($brDigits, 0, 2);
            if ($ddd >= 11 && $ddd <= 99 && $brDigits[2] === '9') {
                return ['55' . $brDigits];
            }
            return [];
        }

        return [];
    }

    /**
     * Returns candidate identities for matching against an incoming canonical identity.
     * Combines exact canonical identity and any Brazilian local/legacy aliases.
     */
    public function candidateIdentities($celular, $telefone = null)
    {
        $identities = [];
        foreach ([$celular, $telefone] as $val) {
            if ($val === null || $val === '') {
                continue;
            }
            $canonical = $this->normalizeCanonicalIdentity($val);
            if ($canonical !== null) {
                $identities[] = $canonical;
            }
            foreach ($this->brazilianIdentityAliases($val) as $alias) {
                $identities[] = $alias;
            }
        }

        return array_values(array_unique($identities));
    }

    /**
     * Performs exact deterministic match between an incoming canonical phone
     * and candidate phone fields.
     */
    public function matchesCandidate($canonicalPhone, $celular, $telefone = null)
    {
        if ($canonicalPhone === null || $canonicalPhone === '') {
            return false;
        }

        return in_array($canonicalPhone, $this->candidateIdentities($celular, $telefone), true);
    }

    /**
     * Normalizes a phone number for outgoing WhatsApp message delivery.
     */
    public function normalizeWhatsApp($value)
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        if (substr($digits, 0, 2) === '55') {
            $identity = $this->normalizeCanonicalIdentity($value);
            return (strlen((string) $identity) === 13) ? $identity : null;
        }

        // Brazilian local fixed line or 10-digit legacy mobile without DDI: reject for WhatsApp delivery
        if (strlen($digits) === 10) {
            return null;
        }

        // Brazilian 11-digit mobile without 55: normalize to 55XXXXXXXXXXX
        if (strlen($digits) === 11) {
            $ddd = (int) substr($digits, 0, 2);
            if ($ddd >= 11 && $ddd <= 99 && $digits[2] === '9') {
                return '55' . $digits;
            }
        }

        // Non-Brazilian international number
        if (strlen($digits) >= 8 && strlen($digits) <= 15) {
            return $digits;
        }

        return null;
    }
}
