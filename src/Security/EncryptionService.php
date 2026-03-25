<?php

namespace Redking\ParseBundle\Security;

/**
 * Encryption/Decryption service with AES-256-CBC and HMAC SHA-256
 *
 * Supports multiple output formats:
 * - 'base64': Standard base64 encoding (recommended for Parse Server)
 * - 'base64url': URL-safe base64 for use in URLs
 * - 'raw': Raw binary (experimental)
 */
class EncryptionService
{
    private const CIPHER_METHOD = 'aes-256-cbc';
    private const IV_LENGTH = 16;
    private const HMAC_LENGTH = 32;
    private const HMAC_ALGO = 'sha256';

    public function __construct(
        private readonly string $encryptionKey,
        private readonly string $hmacKey,
        private readonly string $encoding = 'base64'
    ) {
        if (!in_array($this->encoding, ['base64', 'base64url', 'raw'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid encoding "%s". Must be "base64", "base64url", or "raw".', $this->encoding)
            );
        }
    }

    /**
     * Encrypts a string
     *
     * @param string $plaintext Plaintext to encrypt
     * @return string Encrypted text encoded according to the configured format
     */
    public function encrypt(string $plaintext): string
    {
        // Generate a random IV for each encryption
        $iv = random_bytes(self::IV_LENGTH);

        // Encrypt the text
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_METHOD,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Concatenate IV + ciphertext
        $data = $iv . $ciphertext;

        // Generate HMAC for authentication
        $hmac = hash_hmac(self::HMAC_ALGO, $data, $this->hmacKey, true);

        // Final payload: IV + ciphertext + HMAC
        $payload = $data . $hmac;

        // Encode according to configured format
        return match($this->encoding) {
            'base64url' => $this->encodeBase64Url($payload),
            'base64' => base64_encode($payload),
            'raw' => $payload,
        };
    }

    /**
     * Decrypts a string
     *
     * Automatically detects the encoding format
     *
     * @param string $encrypted Encrypted text
     * @return string|null Plaintext or null on failure
     */
    public function decrypt(string $encrypted): ?string
    {
        // Detect format and decode
        $payload = $this->decodePayload($encrypted);

        if ($payload === null || strlen($payload) <= self::IV_LENGTH + self::HMAC_LENGTH) {
            return null;
        }

        // Extract components
        $data = substr($payload, 0, -self::HMAC_LENGTH);
        $hmac = substr($payload, -self::HMAC_LENGTH);

        // Verify HMAC
        $expectedHmac = hash_hmac(self::HMAC_ALGO, $data, $this->hmacKey, true);

        if (!hash_equals($expectedHmac, $hmac)) {
            // Invalid HMAC = tampered data
            return null;
        }

        // Extract IV and ciphertext
        $iv = substr($data, 0, self::IV_LENGTH);
        $ciphertext = substr($data, self::IV_LENGTH);

        // Decrypt
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER_METHOD,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plaintext !== false ? $plaintext : null;
    }

    /**
     * Encodes data to URL-safe base64
     */
    private function encodeBase64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodes the payload by automatically detecting the format
     */
    private function decodePayload(string $encrypted): ?string
    {
        // Try to detect the format

        // If contains only base64url characters (-_ and alphanumerics)
        if (preg_match('/^[A-Za-z0-9_-]+$/', $encrypted)) {
            // Try base64url
            $decoded = base64_decode(strtr($encrypted, '-_', '+/'), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        // If contains standard base64 characters (+/ or =)
        if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $encrypted)) {
            // Try standard base64
            $decoded = base64_decode($encrypted, true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        // Otherwise consider as raw
        return $encrypted;
    }

    /**
     * Returns the configured encoding format
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }
}
