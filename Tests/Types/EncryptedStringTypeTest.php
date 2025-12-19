<?php

namespace Redking\ParseBundle\Tests\Types;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Security\EncryptionService;
use Redking\ParseBundle\Types\EncryptedStringType;
use Redking\ParseBundle\Types\Type;

class EncryptedStringTypeTest extends TestCase
{
    private const TEST_ENCRYPTION_KEY = 'test_key_32_bytes_long_string!';
    private const TEST_HMAC_KEY = 'test_hmac_32_bytes_long_string!';

    private EncryptionService $encryptionService;
    private EncryptedStringType $type;

    public function setUp(): void
    {
        $this->encryptionService = new EncryptionService(
            self::TEST_ENCRYPTION_KEY,
            self::TEST_HMAC_KEY
        );

        EncryptedStringType::setEncryptionService($this->encryptionService);
        $this->type = Type::getType(Type::ENCRYPTED_STRING);
    }

    public function testConvertToDatabaseValue()
    {
        $plaintext = 'Test value';
        $encrypted = $this->type->convertToDatabaseValue($plaintext);

        $this->assertIsString($encrypted);
        $this->assertNotEquals($plaintext, $encrypted);
    }

    public function testConvertToPHPValue()
    {
        $plaintext = 'Test value';
        $encrypted = $this->type->convertToDatabaseValue($plaintext);
        $decrypted = $this->type->convertToPHPValue($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testConvertNullToDatabaseValue()
    {
        $result = $this->type->convertToDatabaseValue(null);
        $this->assertNull($result);
    }

    public function testConvertNullToPHPValue()
    {
        $result = $this->type->convertToPHPValue(null);
        $this->assertNull($result);
    }

    public function testTypeIsRegistered()
    {
        $this->assertTrue(Type::hasType(Type::ENCRYPTED_STRING));
        $this->assertInstanceOf(EncryptedStringType::class, Type::getType(Type::ENCRYPTED_STRING));
    }

    public function testRoundTripConversion()
    {
        $plaintext = 'Sensitive data with special chars: éèê 中文 العربية';
        $encrypted = $this->type->convertToDatabaseValue($plaintext);
        $decrypted = $this->type->convertToPHPValue($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEmptyStringConversion()
    {
        $plaintext = '';
        $encrypted = $this->type->convertToDatabaseValue($plaintext);
        $decrypted = $this->type->convertToPHPValue($encrypted);

        $this->assertEquals('', $decrypted);
    }

    public function testLongStringConversion()
    {
        $plaintext = str_repeat('Lorem ipsum dolor sit amet ', 100);
        $encrypted = $this->type->convertToDatabaseValue($plaintext);
        $decrypted = $this->type->convertToPHPValue($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testNonDeterministicEncryption()
    {
        $plaintext = 'Same value';
        $encrypted1 = $this->type->convertToDatabaseValue($plaintext);
        $encrypted2 = $this->type->convertToDatabaseValue($plaintext);

        // Should produce different encrypted values
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same value
        $this->assertEquals($plaintext, $this->type->convertToPHPValue($encrypted1));
        $this->assertEquals($plaintext, $this->type->convertToPHPValue($encrypted2));
    }

    public function testInvalidEncryptedDataReturnsNull()
    {
        $invalid = 'not_a_valid_encrypted_string';
        $result = $this->type->convertToPHPValue($invalid);

        $this->assertNull($result);
    }

    public function testNumberConversion()
    {
        $number = 12345;
        $encrypted = $this->type->convertToDatabaseValue($number);
        $decrypted = $this->type->convertToPHPValue($encrypted);

        // Should be converted to string
        $this->assertEquals('12345', $decrypted);
    }
}
