<?php

namespace Redking\ParseBundle\Types;

use Redking\ParseBundle\Security\EncryptionService;

/**
 * Type for automatically encrypted strings
 *
 * Uses EncryptionService to encrypt/decrypt values
 * transparently during persistence operations.
 */
class EncryptedStringType extends Type
{
    /**
     * @var EncryptionService|null
     */
    private static ?EncryptionService $encryptionService = null;

    /**
     * Initializes the encryption service
     *
     * This method must be called at application startup
     * by the ObjectManager via the DI container.
     *
     * @param EncryptionService $service
     */
    public static function setEncryptionService(EncryptionService $service): void
    {
        self::$encryptionService = $service;
    }

    /**
     * Converts a PHP value to database value (encrypted)
     *
     * @param mixed $value The value to convert
     * @return string|null The encrypted value or null
     * @throws \RuntimeException If the encryption service is not initialized
     */
    public function convertToDatabaseValue($value)
    {
        if ($value === null) {
            return null;
        }

        if (self::$encryptionService === null) {
            throw new \RuntimeException(
                'EncryptionService not initialized. Make sure the bundle is properly configured.'
            );
        }

        return self::$encryptionService->encrypt((string) $value);
    }

    /**
     * Converts a database value (encrypted) to PHP value
     *
     * @param mixed $value The encrypted value to convert
     * @return string|null The decrypted value or null
     * @throws \RuntimeException If the encryption service is not initialized
     */
    public function convertToPHPValue($value)
    {
        if ($value === null) {
            return null;
        }

        if (self::$encryptionService === null) {
            throw new \RuntimeException(
                'EncryptionService not initialized. Make sure the bundle is properly configured.'
            );
        }

        $decrypted = self::$encryptionService->decrypt((string) $value);

        // Return null if decryption fails (corrupted data or wrong key)
        return $decrypted;
    }

    /**
     * Returns the closure code for conversion to MongoDB
     *
     * @return string
     */
    public function closureToMongo()
    {
        return '$return = $value;';
    }

    /**
     * Returns the closure code for conversion from MongoDB
     *
     * @return string
     */
    public function closureToPHP()
    {
        return '$return = $value;';
    }
}
