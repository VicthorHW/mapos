<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_phone
{
    /**
     * Normalizes a canonical international identity.
     *
     * Rules:
     * - Strips non-digits and transport characters (+, spaces, dashes, etc.).
     * - Valid boundary: 8 to 15 digits (ITU-T E.164 boundary).
     * - If DDI == 55 (Brazil):
     *   - DDD must be between 11 and 99.
     *   - 11-digit mobile/subscriber structure is preserved (55XXXXXXXXXXX).
     *   - 10-digit legacy mobile ([6-9]) is normalized by inserting '9' (55XX9XXXXXXXX).
     *   - Fixed-line shaped numbers (2-5) are preserved as canonical identities.
     *   - Other lengths or invalid Brazilian structures return null.
     * - If DDI != 55 (International):
     *   - Preserves exact international canonical digits.
     *   - NEVER infers Brazil merely based on length or prefix heuristics.
     *   - NEVER rejects an international canonical identity because it looks like a BR fixed line.
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

            if (strlen($brDigits) === 11) {
                return $digits;
            }
            if (strlen($brDigits) === 10) {
                if (preg_match('/[6-9]/', $brDigits[2])) {
                    return '55' . substr($brDigits, 0, 2) . '9' . substr($brDigits, 2);
                }
                return $digits;
            }

            return null;
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
     * Converts a canonical identity into its MapOS storage representation.
     * Brazilian canonical (55...) remains digits only.
     * Non-Brazilian canonical receives leading '+'.
     */
    public function storageValueFromCanonical($canonical)
    {
        $digits = $this->normalizeCanonicalIdentity($canonical);
        if ($digits === null) {
            return null;
        }

        if (substr($digits, 0, 2) === '55') {
            return $digits;
        }

        return '+' . $digits;
    }

    /**
     * Generates canonical Brazilian aliases for local/legacy inputs without country code.
     *
     * Used ONLY when the input is known to be potentially local Brazilian input
     * (e.g. candidate phone numbers stored in MapOS without country code and without '+').
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
     *
     * Provenance rules:
     * CASE 1: Raw trimmed value begins with '+' -> explicit international canonical.
     *         Does NOT generate Brazilian aliases.
     * CASE 2: Digits explicitly begin with '55' -> canonical Brazilian storage.
     * CASE 3: Unmarked local/legacy storage (no '+', not starting with 55).
     *         Interpreted strictly as Brazilian local/legacy.
     *         Generates Brazilian canonical alias(es). Does NOT treat as international.
     */
    public function candidateIdentities($celular, $telefone = null)
    {
        $identities = [];
        foreach ([$celular, $telefone] as $raw) {
            if ($raw === null) {
                continue;
            }
            $trimmed = trim((string) $raw);
            if ($trimmed === '') {
                continue;
            }

            // CASE 1: Explicit international stored value beginning with '+'
            if (substr($trimmed, 0, 1) === '+') {
                $canonical = $this->normalizeCanonicalIdentity($trimmed);
                if ($canonical !== null) {
                    $identities[] = $canonical;
                }
                continue;
            }

            $digits = preg_replace('/\D+/', '', $trimmed);
            if ($digits === '') {
                continue;
            }

            // CASE 2: Digits explicitly begin with '55' -> Brazilian canonical storage
            if (substr($digits, 0, 2) === '55') {
                $canonical = $this->normalizeCanonicalIdentity($digits);
                if ($canonical !== null) {
                    $identities[] = $canonical;
                }
                continue;
            }

            // CASE 3: Unmarked local/legacy storage -> interpret strictly as Brazilian local legacy
            $aliases = $this->brazilianIdentityAliases($digits);
            foreach ($aliases as $alias) {
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
        $canonical = $this->normalizeCanonicalIdentity($canonicalPhone);
        if ($canonical === null) {
            return false;
        }

        return in_array($canonical, $this->candidateIdentities($celular, $telefone), true);
    }

    /**
     * Normalizes a phone number for outgoing WhatsApp message delivery.
     */
    public function normalizeWhatsApp($value)
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        // Explicit '+' international stored representation
        if (substr($trimmed, 0, 1) === '+') {
            $canonical = $this->normalizeCanonicalIdentity($trimmed);
            return (strlen((string) $canonical) >= 8 && strlen((string) $canonical) <= 15) ? $canonical : null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        // Brazilian canonical storage (starts with 55)
        if (substr($digits, 0, 2) === '55') {
            $canonical = $this->normalizeCanonicalIdentity($digits);
            return (strlen((string) $canonical) === 13) ? $canonical : null;
        }

        // Brazilian local legacy storage (no '+', no '55')
        $aliases = $this->brazilianIdentityAliases($digits);
        if (count($aliases) === 1) {
            return $aliases[0];
        }

        return null;
    }
}
