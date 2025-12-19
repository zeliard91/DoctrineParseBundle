<?php

namespace Redking\ParseBundle\Routing;

use Redking\ParseBundle\Security\EncryptionService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Generates URLs with encrypted object IDs
 *
 * This service wraps the Symfony router to automatically encrypt
 * Parse object IDs before generating URLs, making them safe for
 * public exposure while maintaining security.
 *
 * Example:
 * ```php
 * // Encrypt the 'userId' parameter
 * $url = $generator->generate('user_profile', ['userId' => 'abc123'], 'userId');
 * // Result: /user/profile/dGVzdF9lbmNyeXB0ZWRfZGF0YQ...
 * ```
 */
class EncryptedUrlGenerator
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly EncryptionService $encryptionService
    ) {}

    /**
     * Generates a URL with encrypted ID parameter(s)
     *
     * @param string $routeName The route name
     * @param array $parameters Route parameters (including the ID to encrypt)
     * @param string|array<string> $idKeys The parameter key(s) to encrypt (default: 'id')
     * @param int $referenceType The type of reference to be generated
     * @return string The generated URL with encrypted ID(s)
     * @throws \InvalidArgumentException If a required ID parameter is missing
     */
    public function generate(
        string $routeName,
        array $parameters,
        string|array $idKeys = 'id',
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string {
        $idKeys = is_array($idKeys) ? $idKeys : [$idKeys];

        foreach ($idKeys as $idKey) {
            if (!isset($parameters[$idKey])) {
                throw new \InvalidArgumentException(
                    sprintf("Missing parameter '%s' for encrypted URL generation.", $idKey)
                );
            }

            $parameters[$idKey] = $this->encryptionService->encrypt((string) $parameters[$idKey]);
        }

        return $this->router->generate($routeName, $parameters, $referenceType);
    }
}
