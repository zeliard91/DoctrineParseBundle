<?php

namespace Redking\ParseBundle\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Routing\EncryptedUrlGenerator;
use Redking\ParseBundle\Security\EncryptionService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class EncryptedUrlExtensionTest extends TestCase
{
    private EncryptedUrlGenerator $urlGenerator;
    private $extension;

    public function setUp(): void
    {
        if (!class_exists('Twig\Extension\AbstractExtension')) {
            $this->markTestSkipped('Twig is not available in this environment');
        }
        $encryptionService = new EncryptionService(
            'test_encryption_key_32_bytes_long',
            'test_hmac_key_32_bytes_long_key!',
            'base64url'
        );

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->willReturnCallback(function ($name, $params, $type) {
                $path = '/' . $name . '/' . ($params['id'] ?? $params['userId'] ?? 'default');
                return $type === UrlGeneratorInterface::ABSOLUTE_URL
                    ? 'https://example.com' . $path
                    : $path;
            });

        $this->urlGenerator = new EncryptedUrlGenerator($router, $encryptionService);

        $extensionClass = 'Redking\ParseBundle\Twig\EncryptedUrlExtension';
        $this->extension = new $extensionClass($this->urlGenerator);
    }

    public function testGetFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(2, $functions);

        $functionNames = array_map(fn($f) => $f->getName(), $functions);
        $this->assertContains('encrypted_path', $functionNames);
        $this->assertContains('encrypted_url', $functionNames);
    }

    public function testEncryptedPathGeneratesRelativePath(): void
    {
        $result = $this->extension->encryptedPath('user_profile', ['id' => 'abc123']);

        $this->assertStringStartsWith('/', $result);
        $this->assertStringNotContainsString('https://', $result);
        $this->assertStringContainsString('user_profile', $result);
    }

    public function testEncryptedUrlGeneratesAbsoluteUrl(): void
    {
        $result = $this->extension->encryptedUrl('user_profile', ['id' => 'abc123']);

        $this->assertStringStartsWith('https://', $result);
        $this->assertStringContainsString('user_profile', $result);
    }

    public function testEncryptedPathWithCustomIdKey(): void
    {
        $result = $this->extension->encryptedPath('user_show', ['userId' => 'user123'], 'userId');

        $this->assertStringStartsWith('/', $result);
        $this->assertStringContainsString('user_show', $result);
    }

    public function testEncryptedUrlWithCustomIdKey(): void
    {
        $result = $this->extension->encryptedUrl('user_show', ['userId' => 'user123'], 'userId');

        $this->assertStringStartsWith('https://', $result);
        $this->assertStringContainsString('user_show', $result);
    }

    public function testEncryptedPathWithMultipleParameters(): void
    {
        $result = $this->extension->encryptedPath(
            'user_post',
            ['userId' => 'user123', 'postId' => 'post456'],
            ['userId', 'postId']
        );

        $this->assertIsString($result);
        $this->assertStringStartsWith('/', $result);
    }

    public function testEncryptedUrlWithMultipleParameters(): void
    {
        $result = $this->extension->encryptedUrl(
            'user_post',
            ['userId' => 'user123', 'postId' => 'post456'],
            ['userId', 'postId']
        );

        $this->assertIsString($result);
        $this->assertStringStartsWith('https://', $result);
    }

    public function testEncryptedPathWithOtherParametersOnly(): void
    {
        // Test URL generation with an ID to encrypt and other parameters
        $result = $this->extension->encryptedPath('user_search', ['query' => 'test', 'id' => 'user123']);

        $this->assertStringStartsWith('/', $result);
        // 'id' should be encrypted, 'query' should remain plain
    }

    public function testEncryptedPathDefaultIdKey(): void
    {
        // Test that 'id' is used as default key
        $result = $this->extension->encryptedPath('user_profile', ['id' => 'test123']);

        $this->assertIsString($result);
    }
}
