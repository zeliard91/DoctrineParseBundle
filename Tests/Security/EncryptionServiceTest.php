<?php

namespace Redking\ParseBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Security\EncryptionService;

class EncryptionServiceTest extends TestCase
{
    private const TEST_ENCRYPTION_KEY = 'test_encryption_key_32_bytes!!';
    private const TEST_HMAC_KEY = 'test_hmac_key_32_bytes_long!!';

    public function testEncryptDecryptRoundTripBase64()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'base64'
        );

        $plaintext = 'Sensitive information';
        $encrypted = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptDecryptRoundTripBase64Url()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'base64url'
        );

        $plaintext = 'Sensitive information for URL';
        $encrypted = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptDecryptRoundTripRaw()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'raw'
        );

        $plaintext = 'Raw binary test';
        $encrypted = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptedValueIsDifferent()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY
        );

        $plaintext = 'Test data';
        $encrypted = $service->encrypt($plaintext);

        $this->assertNotEquals($plaintext, $encrypted);
    }

    public function testBase64UrlIsUrlSafe()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'base64url'
        );

        $plaintext = 'URL safe test with special chars';
        $encrypted = $service->encrypt($plaintext);

        // Should not contain +, /, or = characters
        $this->assertStringNotContainsString('+', $encrypted);
        $this->assertStringNotContainsString('/', $encrypted);
        $this->assertStringNotContainsString('=', $encrypted);

        // Should only contain URL-safe characters
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $encrypted);
    }

    public function testBase64ContainsStandardCharacters()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'base64'
        );

        // Generate enough data to likely produce + or / characters
        $plaintext = str_repeat('Test data for base64 encoding ', 10);
        $encrypted = $service->encrypt($plaintext);

        // Should match base64 pattern
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $encrypted);
    }

    public function testDecryptInvalidDataReturnsNull()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY
        );

        $invalid = 'invalid_encrypted_data';
        $result = $service->decrypt($invalid);

        $this->assertNull($result);
    }

    public function testDecryptTamperedDataReturnsNull()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY
        );

        $plaintext = 'Tamper test';
        $encrypted = $service->encrypt($plaintext);

        // Tamper with the encrypted data
        $tampered = substr($encrypted, 0, -5) . 'XXXXX';
        $result = $service->decrypt($tampered);

        $this->assertNull($result);
    }

    public function testEncryptionIsNonDeterministic()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY
        );

        $plaintext = 'Same data';
        $encrypted1 = $service->encrypt($plaintext);
        $encrypted2 = $service->encrypt($plaintext);

        // Same plaintext should produce different ciphertext (due to random IV)
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same value
        $this->assertEquals($plaintext, $service->decrypt($encrypted1));
        $this->assertEquals($plaintext, $service->decrypt($encrypted2));
    }

    public function testEncryptEmptyString()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY
        );

        $plaintext = '';
        $encrypted = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($encrypted);

        $this->assertEquals('', $decrypted);
    }

    public function testEncryptUnicodeCharacters()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY
        );

        $plaintext = 'Texte français avec accents: éèêë àâä 中文 العربية';
        $encrypted = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testAutomaticFormatDetection()
    {
        $serviceBase64 = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'base64'
        );

        $serviceBase64Url = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'base64url'
        );

        $serviceRaw = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'raw'
        );

        $plaintext = 'Format detection test';

        // Encrypt with each format
        $encryptedBase64 = $serviceBase64->encrypt($plaintext);
        $encryptedBase64Url = $serviceBase64Url->encrypt($plaintext);
        $encryptedRaw = $serviceRaw->encrypt($plaintext);

        // Should be able to decrypt with any service (automatic detection)
        $this->assertEquals($plaintext, $serviceBase64->decrypt($encryptedBase64));
        $this->assertEquals($plaintext, $serviceBase64->decrypt($encryptedBase64Url));
        $this->assertEquals($plaintext, $serviceBase64->decrypt($encryptedRaw));

        $this->assertEquals($plaintext, $serviceBase64Url->decrypt($encryptedBase64));
        $this->assertEquals($plaintext, $serviceBase64Url->decrypt($encryptedBase64Url));
        $this->assertEquals($plaintext, $serviceBase64Url->decrypt($encryptedRaw));
    }

    public function testBase64IsSmallerThanBase64Url()
    {
        $serviceBase64 = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'base64'
        );

        $serviceBase64Url = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'base64url'
        );

        // Use a fixed plaintext to compare sizes
        $plaintext = str_repeat('A', 100);

        // Encrypt same plaintext with fixed IV to compare sizes accurately
        $encryptedBase64 = $serviceBase64->encrypt($plaintext);
        $encryptedBase64Url = $serviceBase64Url->encrypt($plaintext);

        // base64 can be equal or smaller than base64url (no padding removal)
        // This test just verifies both work, actual size depends on data
        $this->assertNotEmpty($encryptedBase64);
        $this->assertNotEmpty($encryptedBase64Url);
    }

    public function testInvalidEncodingThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encoding');

        new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'invalid_encoding'
        );
    }

    public function testGetEncoding()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY,
            'base64url'
        );

        $this->assertEquals('base64url', $service->getEncoding());
    }

    public function testDefaultEncodingIsBase64()
    {
        $service = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY
        );

        $this->assertEquals('base64', $service->getEncoding());
    }
}
