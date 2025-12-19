<?php

namespace Redking\ParseBundle\Twig;

use Redking\ParseBundle\Routing\EncryptedUrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for generating encrypted URLs
 *
 * Provides Twig functions to generate URLs with encrypted Parse object IDs,
 * making it easy to create secure public links in templates.
 *
 * Available functions:
 * - encrypted_path(): Generates a relative path with encrypted ID
 * - encrypted_url(): Generates an absolute URL with encrypted ID
 */
class EncryptedUrlExtension extends AbstractExtension
{
    public function __construct(
        private readonly EncryptedUrlGenerator $urlGenerator
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('encrypted_path', [$this, 'encryptedPath']),
            new TwigFunction('encrypted_url', [$this, 'encryptedUrl']),
        ];
    }

    /**
     * Generates a relative path with encrypted ID(s)
     *
     * @param string $routeName The route name
     * @param array $parameters Route parameters
     * @param string|array<string> $idKeys Parameter key(s) to encrypt (default: 'id')
     * @return string The generated path
     */
    public function encryptedPath(string $routeName, array $parameters = [], string|array $idKeys = 'id'): string
    {
        return $this->urlGenerator->generate(
            $routeName,
            $parameters,
            $idKeys,
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    /**
     * Generates an absolute URL with encrypted ID(s)
     *
     * @param string $routeName The route name
     * @param array $parameters Route parameters
     * @param string|array<string> $idKeys Parameter key(s) to encrypt (default: 'id')
     * @return string The generated URL
     */
    public function encryptedUrl(string $routeName, array $parameters = [], string|array $idKeys = 'id'): string
    {
        return $this->urlGenerator->generate(
            $routeName,
            $parameters,
            $idKeys,
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
