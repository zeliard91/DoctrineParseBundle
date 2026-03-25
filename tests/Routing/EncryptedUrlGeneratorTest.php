<?php

namespace Redking\ParseBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Routing\EncryptedUrlGenerator;
use Redking\ParseBundle\Security\EncryptionService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class EncryptedUrlGeneratorTest extends TestCase
{
    private EncryptionService $encryptionService;
    private RouterInterface $router;
    private EncryptedUrlGenerator $generator;

    public function setUp(): void
    {
        $this->encryptionService = new EncryptionService(
            'test_encryption_key_32_bytes_long',
            'test_hmac_key_32_bytes_long_key!',
            'base64url'
        );

        $this->router = $this->createMock(RouterInterface::class);
        $this->generator = new EncryptedUrlGenerator($this->router, $this->encryptionService);
    }

    public function testGenerateEncryptsIdParameter(): void
    {
        $plainId = 'abc123xyz';

        $this->router->expects($this->once())
            ->method('generate')
            ->with(
                'user_profile',
                $this->callback(function ($params) use ($plainId) {
                    // Check that 'id' is encrypted (not the plain value)
                    $this->assertNotEquals($plainId, $params['id']);
                    // Check that 'other' parameter is preserved
                    $this->assertEquals('value', $params['other']);
                    // Check that encrypted value can be decrypted back to original
                    $decrypted = $this->encryptionService->decrypt($params['id']);
                    $this->assertEquals($plainId, $decrypted);
                    return true;
                }),
                UrlGeneratorInterface::ABSOLUTE_PATH
            )
            ->willReturn('/user/profile/encrypted_id');

        $result = $this->generator->generate('user_profile', ['id' => $plainId, 'other' => 'value']);

        $this->assertStringContainsString('/user/profile/', $result);
    }

    public function testGenerateWithCustomIdKey(): void
    {
        $plainId = 'user123';

        $this->router->expects($this->once())
            ->method('generate')
            ->with(
                'user_show',
                $this->callback(function ($params) use ($plainId) {
                    $this->assertNotEquals($plainId, $params['userId']);
                    $decrypted = $this->encryptionService->decrypt($params['userId']);
                    $this->assertEquals($plainId, $decrypted);
                    return true;
                }),
                UrlGeneratorInterface::ABSOLUTE_PATH
            )
            ->willReturn('/user/encrypted_id');

        $result = $this->generator->generate('user_show', ['userId' => $plainId], 'userId');

        $this->assertStringContainsString('/user/', $result);
    }

    public function testGenerateWithMultipleIdKeys(): void
    {
        $userId = 'user123';
        $postId = 'post456';

        $this->router->expects($this->once())
            ->method('generate')
            ->with(
                'user_post',
                $this->callback(function ($params) use ($userId, $postId) {
                    $this->assertNotEquals($userId, $params['userId']);
                    $this->assertNotEquals($postId, $params['postId']);
                    $this->assertEquals($userId, $this->encryptionService->decrypt($params['userId']));
                    $this->assertEquals($postId, $this->encryptionService->decrypt($params['postId']));
                    return true;
                }),
                UrlGeneratorInterface::ABSOLUTE_PATH
            )
            ->willReturn("/user/encrypted_user/post/encrypted_post");

        $result = $this->generator->generate(
            'user_post',
            ['userId' => $userId, 'postId' => $postId],
            ['userId', 'postId']
        );

        $this->assertIsString($result);
    }

    public function testGenerateWithAbsoluteUrl(): void
    {
        $plainId = 'abc123';

        $this->router->expects($this->once())
            ->method('generate')
            ->with(
                'user_profile',
                $this->callback(function ($params) use ($plainId) {
                    $this->assertNotEquals($plainId, $params['id']);
                    $this->assertEquals($plainId, $this->encryptionService->decrypt($params['id']));
                    return true;
                }),
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.com/user/profile/encrypted_id');

        $result = $this->generator->generate(
            'user_profile',
            ['id' => $plainId],
            'id',
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->assertStringStartsWith('https://', $result);
    }

    public function testGenerateThrowsExceptionWhenIdParameterMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing parameter 'id'");

        $this->generator->generate('user_profile', ['other' => 'value']);
    }

    public function testGenerateThrowsExceptionWhenCustomIdKeyMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing parameter 'userId'");

        $this->generator->generate('user_profile', ['id' => '123'], 'userId');
    }

    public function testGeneratePreservesOtherParameters(): void
    {
        $plainId = 'abc123';

        $this->router->expects($this->once())
            ->method('generate')
            ->with(
                'user_profile',
                $this->callback(function ($params) use ($plainId) {
                    $this->assertNotEquals($plainId, $params['id']);
                    $this->assertEquals($plainId, $this->encryptionService->decrypt($params['id']));
                    $this->assertEquals('settings', $params['tab']);
                    $this->assertEquals(2, $params['page']);
                    return true;
                }),
                UrlGeneratorInterface::ABSOLUTE_PATH
            )
            ->willReturn('/user/profile/encrypted_id?tab=settings&page=2');

        $result = $this->generator->generate(
            'user_profile',
            ['id' => $plainId, 'tab' => 'settings', 'page' => 2]
        );

        $this->assertIsString($result);
    }

    public function testGeneratedIdIsUrlSafe(): void
    {
        $plainId = 'abc123';

        $this->router->expects($this->once())
            ->method('generate')
            ->with(
                $this->anything(),
                $this->callback(function ($params) {
                    $encryptedId = $params['id'];
                    // URL-safe base64 should not contain +, /, or =
                    return !str_contains($encryptedId, '+') &&
                           !str_contains($encryptedId, '/') &&
                           !str_contains($encryptedId, '=');
                }),
                $this->anything()
            )
            ->willReturn('/user/test');

        $this->generator->generate('user_profile', ['id' => $plainId]);
    }
}
