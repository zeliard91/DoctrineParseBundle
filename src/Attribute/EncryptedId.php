<?php

namespace Redking\ParseBundle\Attribute;

use Attribute;

/**
 * Used to decrypt object IDs in route parameters
 *
 * This attribute marks a route parameter as containing an encrypted Parse object ID.
 * The EncryptedIdValueResolver will automatically decrypt it and load the corresponding entity.
 *
 * Example:
 * ```php
 * #[Route('/user/{user}', name: 'user_profile')]
 * public function profile(#[EncryptedId(User::class)] User $user): Response
 * {
 *     // $user is automatically decrypted and loaded
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class EncryptedId
{
    /**
     * @param string $class The fully qualified class name of the Parse object
     */
    public function __construct(
        public readonly string $class
    ) {}
}
