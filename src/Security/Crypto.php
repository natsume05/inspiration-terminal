<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Authenticated symmetric encryption for private notes.
 *
 * The previous implementation claimed notes were encrypted while storing them
 * as plaintext. This class makes the claim true: notes are sealed with
 * AES-256-GCM, which authenticates the ciphertext so tampering is detected on
 * read rather than silently decrypting to garbage.
 *
 * The key is derived from `APP_KEY` through HKDF-SHA256 and never touches the
 * database, so a database dump alone cannot reveal note contents.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private string $key;

    /**
     * @param string $appKey Secret application key.
     * @param string $info Domain separation label for key derivation.
     */
    public function __construct(string $appKey, string $info = 'inspiration-terminal:private-notes')
    {
        if (strlen($appKey) < 16) {
            throw new RuntimeException('APP_KEY must be at least 16 characters.');
        }

        if (!in_array(self::CIPHER, openssl_get_cipher_methods(), true)) {
            throw new RuntimeException(sprintf('Cipher %s is unavailable in this OpenSSL build.', self::CIPHER));
        }

        // hkdf returns false on failure; separated derivation keeps this key
        // independent from any other use of APP_KEY.
        $derived = hash_hkdf('sha256', $appKey, 32, $info);

        if ($derived === false) {
            throw new RuntimeException('Failed to derive an encryption key.');
        }

        $this->key = $derived;
    }

    /**
     * Encrypt a plaintext string.
     *
     * @param string $plaintext The secret to protect.
     * @return array{ciphertext: string, nonce: string} Values to persist.
     */
    public function encrypt(string $plaintext): array
    {
        if ($plaintext === '') {
            throw new RuntimeException('Refusing to encrypt an empty value.');
        }

        // A fresh random nonce per message is mandatory for GCM: reusing a
        // nonce with the same key destroys both confidentiality and integrity.
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        // The authentication tag is appended to the ciphertext so both are
        // stored in one column and verified together on read.
        return [
            'ciphertext' => $ciphertext . $tag,
            'nonce' => $nonce,
        ];
    }

    /**
     * Decrypt a value produced by {@see self::encrypt()}.
     *
     * @param string $ciphertext Ciphertext with the appended authentication tag.
     * @param string $nonce The nonce used during encryption.
     * @return string The recovered plaintext.
     */
    public function decrypt(string $ciphertext, string $nonce): string
    {
        if (strlen($nonce) !== self::NONCE_LENGTH) {
            throw new RuntimeException('Malformed nonce.');
        }

        if (strlen($ciphertext) <= self::TAG_LENGTH) {
            throw new RuntimeException('Malformed ciphertext.');
        }

        $tag = substr($ciphertext, -self::TAG_LENGTH);
        $payload = substr($ciphertext, 0, -self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $payload,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($plaintext === false) {
            // Either the key changed or the stored value was modified. Both are
            // reported the same way so the message reveals nothing.
            throw new RuntimeException('Decryption failed: the key is wrong or the data was altered.');
        }

        return $plaintext;
    }
}
