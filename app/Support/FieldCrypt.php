<?php

namespace App\Support;

use RuntimeException;

class FieldCrypt
{
    public const PREFIX = 'enc:v1:';

    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (self::isEncrypted($value)) {
            return $value;
        }

        $key = self::currentKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', $key['raw'], OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipher === false || $tag === '') {
            throw new RuntimeException('Unable to encrypt data.');
        }

        return self::PREFIX.$key['id'].':'.self::b64($iv).':'.self::b64($tag).':'.self::b64($cipher);
    }

    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! self::isEncrypted($value)) {
            return $value;
        }

        $parts = explode(':', $value);
        if (count($parts) !== 6 || $parts[0] !== 'enc' || $parts[1] !== 'v1') {
            throw new RuntimeException('Invalid ciphertext.');
        }

        $key = self::keyById($parts[2]);
        $plain = openssl_decrypt(self::unb64($parts[5]), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, self::unb64($parts[3]), self::unb64($parts[4]));

        if ($plain === false) {
            throw new RuntimeException('Unable to decrypt data.');
        }

        return $plain;
    }

    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public static function blindIndex(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, self::searchKey());
    }

    public static function normalizePhone(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }

    public static function normalizeEmail(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_strtolower(trim($value));
    }

    public static function normalizeNationalId(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_strtoupper(preg_replace('/\s+/', '', trim($value)));
    }

    public static function phoneTail(?string $normalized): ?string
    {
        if ($normalized === null || strlen($normalized) < 4) {
            return null;
        }

        return substr($normalized, -4);
    }

    public static function reencrypt(?string $value): ?string
    {
        $plain = self::decrypt($value);

        return $plain === null ? null : self::encrypt($plain);
    }

    public static function currentKey(): array
    {
        $id = (string) config('hms.encryption.key_id', 'k1');

        return ['id' => $id, 'raw' => self::decodeKey(self::configuredKey())];
    }

    private static function configuredKey(): string
    {
        $key = config('hms.encryption.key');

        if (is_string($key) && $key !== '') {
            return $key;
        }

        if (config('hms.encryption.require_dedicated_keys')) {
            throw new RuntimeException('HMS_ENCRYPTION_KEY must be set in production.');
        }

        return 'base64:'.base64_encode(hash('sha256', (string) config('app.key').'|hms-data-key', true));
    }

    private static function searchKey(): string
    {
        $key = config('hms.encryption.search_key');

        if (is_string($key) && $key !== '') {
            return self::decodeKey($key);
        }

        if (config('hms.encryption.require_dedicated_keys')) {
            throw new RuntimeException('HMS_SEARCH_KEY must be set in production.');
        }

        return hash('sha256', (string) config('app.key').'|hms-search-key', true);
    }

    private static function keyById(string $id): string
    {
        $current = self::currentKey();
        if ($current['id'] === $id) {
            return $current['raw'];
        }

        foreach (self::previousKeys() as $kid => $raw) {
            if ($kid === $id) {
                return $raw;
            }
        }

        throw new RuntimeException('Encryption key is not available.');
    }

    private static function previousKeys(): array
    {
        $raw = (string) config('hms.encryption.previous_keys', '');
        if ($raw === '') {
            return [];
        }

        $keys = [];
        foreach (explode(',', $raw) as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $keys[trim($parts[0])] = self::decodeKey(trim($parts[1]));
        }

        return $keys;
    }

    private static function decodeKey(string $value): string
    {
        if (str_starts_with($value, 'base64:')) {
            $decoded = base64_decode(substr($value, 7), true);
            if ($decoded === false || strlen($decoded) !== 32) {
                throw new RuntimeException('Invalid encryption key.');
            }

            return $decoded;
        }

        $decoded = base64_decode($value, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }

        if (strlen($value) === 32) {
            return $value;
        }

        throw new RuntimeException('Invalid encryption key.');
    }

    public static function targets(): array
    {
        return [
            'patients' => [
                'phone', 'email', 'national_id', 'address', 'emergency_contact_name',
                'emergency_contact_phone', 'next_of_kin_name', 'next_of_kin_phone', 'notes',
            ],
            'encounters' => ['chief_complaint', 'notes'],
            'clinical_notes' => ['body'],
            'care_plans' => ['body'],
            'diagnoses' => ['name', 'code'],
            'patient_allergies' => ['allergen', 'reaction'],
            'patient_conditions' => ['name', 'notes'],
            'vitals' => ['notes'],
            'service_orders' => ['result', 'notes'],
            'referrals' => ['reason', 'response_notes', 'patient_name'],
            'ambulance_trips' => ['notes', 'handover_notes'],
            'prescriptions' => ['notes'],
            'clinical_documents' => ['original_name'],
        ];
    }

    private static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function unb64(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid ciphertext encoding.');
        }

        return $decoded;
    }
}
