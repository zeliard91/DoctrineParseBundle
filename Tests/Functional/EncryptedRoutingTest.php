<?php

namespace Redking\ParseBundle\Tests\Functional;

use Redking\ParseBundle\Routing\EncryptedUrlGenerator;
use Redking\ParseBundle\Security\EncryptionService;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\TestCase;

/**
 * Functional test for the encrypted routing feature
 *
 * Tests the complete flow:
 * 1. Create a Parse object with an ID
 * 2. Generate an encrypted URL for it
 * 3. Decrypt the ID and retrieve the object
 */
class EncryptedRoutingTest extends TestCase
{
    protected static $modelSets = [Post::class];

    private EncryptionService $encryptionService;
    private EncryptedUrlGenerator $urlGenerator;

    public function setUp(): void
    {
        parent::setUp();

        // Use base64url encoding for URLs
        $this->encryptionService = new EncryptionService(
            'test_encryption_key_32_bytes_for_tests!',
            'test_hmac_key_32_bytes_for_tests!!',
            'base64url'
        );

        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $router->method('generate')
            ->willReturnCallback(function ($name, $params, $type) {
                return '/post/' . $params['postId'];
            });

        $this->urlGenerator = new EncryptedUrlGenerator($router, $this->encryptionService);
    }

    public function testCompleteEncryptedRoutingFlow(): void
    {
        // Step 1: Create a Parse object
        $post = new Post();
        $post->setText('Test Post for Encrypted Routing');

        $this->om->persist($post);
        $this->om->flush();

        $originalId = $post->getId();
        $this->assertNotEmpty($originalId);

        // Step 2: Generate encrypted URL
        $encryptedUrl = $this->urlGenerator->generate(
            'post_show',
            ['postId' => $originalId],
            'postId'
        );

        $this->assertStringContainsString('/post/', $encryptedUrl);

        // Step 3: Extract encrypted ID from URL
        $parts = explode('/post/', $encryptedUrl);
        $encryptedId = $parts[1];

        // Verify encrypted ID is URL-safe
        $this->assertStringNotContainsString('+', $encryptedId, 'Encrypted ID should be URL-safe');
        $this->assertStringNotContainsString('/', $encryptedId, 'Encrypted ID should be URL-safe');
        $this->assertStringNotContainsString('=', $encryptedId, 'Encrypted ID should be URL-safe');

        // Step 4: Decrypt and verify
        $decryptedId = $this->encryptionService->decrypt($encryptedId);
        $this->assertEquals($originalId, $decryptedId);

        // Step 5: Load object using decrypted ID
        $loadedPost = $this->om->getRepository(Post::class)->find($decryptedId);
        $this->assertNotNull($loadedPost);
        $this->assertEquals($post->getId(), $loadedPost->getId());
        $this->assertEquals('Test Post for Encrypted Routing', $loadedPost->getText());
    }

    public function testEncryptedIdIsNotReversibleWithoutKeys(): void
    {
        // Create a post
        $post = new Post();
        $post->setText('Secret Post');
        $this->om->persist($post);
        $this->om->flush();

        $originalId = $post->getId();

        // Encrypt with our service
        $encryptedId = $this->encryptionService->encrypt($originalId);

        // Try to decrypt with wrong keys (should fail)
        $wrongEncryptionService = new EncryptionService(
            'wrong_encryption_key_32_bytes!!',
            'wrong_hmac_key_32_bytes_for_test!',
            'base64url'
        );

        $decryptedId = $wrongEncryptionService->decrypt($encryptedId);
        $this->assertNull($decryptedId, 'Should not be able to decrypt with wrong keys');
    }

    public function testMultipleObjectsHaveDifferentEncryptedIds(): void
    {
        // Create two posts
        $post1 = new Post();
        $post1->setText('Post 1');
        $this->om->persist($post1);

        $post2 = new Post();
        $post2->setText('Post 2');
        $this->om->persist($post2);

        $this->om->flush();

        // Encrypt both IDs
        $encrypted1 = $this->encryptionService->encrypt($post1->getId());
        $encrypted2 = $this->encryptionService->encrypt($post2->getId());

        // They should be different
        $this->assertNotEquals($encrypted1, $encrypted2);

        // Both should decrypt correctly
        $this->assertEquals($post1->getId(), $this->encryptionService->decrypt($encrypted1));
        $this->assertEquals($post2->getId(), $this->encryptionService->decrypt($encrypted2));
    }

    public function testEncryptedIdIsNonDeterministic(): void
    {
        $post = new Post();
        $post->setText('Test Post');
        $this->om->persist($post);
        $this->om->flush();

        $id = $post->getId();

        // Encrypt the same ID twice
        $encrypted1 = $this->encryptionService->encrypt($id);
        $encrypted2 = $this->encryptionService->encrypt($id);

        // Due to random IV, encryptions should differ
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same value
        $this->assertEquals($id, $this->encryptionService->decrypt($encrypted1));
        $this->assertEquals($id, $this->encryptionService->decrypt($encrypted2));
    }

    public function testTamperedEncryptedIdReturnsNull(): void
    {
        $post = new Post();
        $post->setText('Test Post');
        $this->om->persist($post);
        $this->om->flush();

        $encryptedId = $this->encryptionService->encrypt($post->getId());

        // Tamper with the encrypted ID (flip multiple characters in the middle)
        // This will corrupt the HMAC or the ciphertext, causing verification to fail
        $middle = (int) (strlen($encryptedId) / 2);
        $tamperedId = substr($encryptedId, 0, $middle - 2) . 'XX' . substr($encryptedId, $middle);

        // Should return null (HMAC verification fails or decryption fails)
        $decrypted = $this->encryptionService->decrypt($tamperedId);
        $this->assertNull($decrypted, 'Tampered encrypted ID should not decrypt successfully');
    }

    public function testUrlGeneratorWithMultipleIds(): void
    {
        // Create two posts
        $post1 = new Post();
        $post1->setText('Post 1');
        $this->om->persist($post1);

        $post2 = new Post();
        $post2->setText('Post 2');
        $this->om->persist($post2);

        $this->om->flush();

        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $router->method('generate')
            ->willReturnCallback(function ($name, $params, $type) {
                return '/compare/' . $params['post1'] . '/' . $params['post2'];
            });

        $urlGenerator = new EncryptedUrlGenerator($router, $this->encryptionService);

        // Generate URL with both IDs encrypted
        $url = $urlGenerator->generate(
            'post_compare',
            ['post1' => $post1->getId(), 'post2' => $post2->getId()],
            ['post1', 'post2']
        );

        $this->assertStringContainsString('/compare/', $url);

        // Extract and decrypt both IDs
        preg_match('/\/compare\/([^\/]+)\/([^\/]+)/', $url, $matches);
        $encrypted1 = $matches[1];
        $encrypted2 = $matches[2];

        $decrypted1 = $this->encryptionService->decrypt($encrypted1);
        $decrypted2 = $this->encryptionService->decrypt($encrypted2);

        $this->assertEquals($post1->getId(), $decrypted1);
        $this->assertEquals($post2->getId(), $decrypted2);
    }
}
