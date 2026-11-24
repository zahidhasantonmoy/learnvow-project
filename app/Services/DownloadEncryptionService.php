<?php

namespace App\Services;

use App\Models\User;

/**
 * Encrypted downloads (spec §5.4, A11): AES-256-CBC, per-user key
 * derived with HKDF from the app key + user id. Key rotation is NOT
 * decided — key_id 'hkdf-v1' is recorded on the download row so a
 * future rotation can tell which key encrypted it. This is not hard
 * DRM: anyone who can read can screenshot.
 */
class DownloadEncryptionService
{
    public const KEY_ID = 'hkdf-v1';

    public const ALGORITHM = 'AES-256-CBC';

    /** 32-byte per-user key, derived — never stored, never in a URL. */
    public function keyFor(User $user): string
    {
        return hash_hkdf('sha256', (string) config('app.key'), 32, 'learnvow-download-key', (string) $user->id);
    }

    public function keyId(): string
    {
        return self::KEY_ID;
    }

    /** Encrypt: base64(iv || ciphertext). IV is random per download. */
    public function encrypt(string $plaintext, User $user): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::ALGORITHM));
        $cipher = openssl_encrypt($plaintext, self::ALGORITHM, $this->keyFor($user), OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv.$cipher);
    }

    public function decrypt(string $payload, User $user): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= 16) {
            throw new \InvalidArgumentException('Invalid encrypted payload.');
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, self::ALGORITHM, $this->keyFor($user), OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new \InvalidArgumentException('Decryption failed.');
        }

        return $plain;
    }

    /** Key material for the download-key endpoint (base64, auth + canRead checked by the caller). */
    public function downloadKey(User $user): array
    {
        return [
            'key' => base64_encode($this->keyFor($user)),
            'key_id' => self::KEY_ID,
            'algorithm' => self::ALGORITHM,
        ];
    }

    public function encryptedDownloadsEnabled(): bool
    {
        return (new BillingService)->setting('protection.encrypted_downloads', '0') === '1';
    }
}
