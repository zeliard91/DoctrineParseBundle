<?php

namespace Redking\ParseBundle\Tests\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\ArgumentResolver\EncryptedIdValueResolver;
use Redking\ParseBundle\Attribute\EncryptedId;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\ObjectRepository;
use Redking\ParseBundle\Security\EncryptionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EncryptedIdValueResolverTest extends TestCase
{
    private EncryptionService $encryptionService;
    private ObjectManager $om;
    private EncryptedIdValueResolver $resolver;

    public function setUp(): void
    {
        $this->encryptionService = new EncryptionService(
            'test_encryption_key_32_bytes_long',
            'test_hmac_key_32_bytes_long_key!',
            'base64url'
        );

        $this->om = $this->createMock(ObjectManager::class);
        $this->resolver = new EncryptedIdValueResolver($this->om, $this->encryptionService);
    }

    public function testResolveWithEncryptedId(): void
    {
        $plainId = 'abc123';
        $encryptedId = $this->encryptionService->encrypt($plainId);

        $mockEntity = new \stdClass();
        $mockEntity->id = $plainId;

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($plainId)
            ->willReturn($mockEntity);

        $this->om->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\User')
            ->willReturn($repository);

        $request = new Request();
        $request->attributes->set('user', $encryptedId);

        $argument = new ArgumentMetadata(
            'user',
            'App\Entity\User',
            false,
            false,
            null,
            false,
            [new EncryptedId('App\Entity\User')]
        );

        $result = $this->resolver->resolve($request, $argument);
        $result = iterator_to_array($result);

        $this->assertCount(1, $result);
        $this->assertSame($mockEntity, $result[0]);
    }

    public function testResolveReturnsEmptyWhenNoEncryptedIdAttribute(): void
    {
        $request = new Request();
        $argument = new ArgumentMetadata('user', 'App\Entity\User', false, false, null);

        $result = $this->resolver->resolve($request, $argument);
        $result = iterator_to_array($result);

        $this->assertCount(0, $result);
    }

    public function testResolveReturnsEmptyWhenParameterNotInRequest(): void
    {
        $request = new Request();
        // No 'user' attribute set

        $argument = new ArgumentMetadata(
            'user',
            'App\Entity\User',
            false,
            false,
            null,
            false,
            [new EncryptedId('App\Entity\User')]
        );

        $result = $this->resolver->resolve($request, $argument);
        $result = iterator_to_array($result);

        $this->assertCount(0, $result);
    }

    public function testResolveThrowsNotFoundWhenDecryptionFails(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Invalid encrypted ID');

        $request = new Request();
        $request->attributes->set('user', 'invalid_encrypted_data');

        $argument = new ArgumentMetadata(
            'user',
            'App\Entity\User',
            false,
            false,
            null,
            false,
            [new EncryptedId('App\Entity\User')]
        );

        $result = $this->resolver->resolve($request, $argument);
        iterator_to_array($result); // Force evaluation
    }

    public function testResolveThrowsNotFoundWhenEntityNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('No entity "App\Entity\User" found for ID');

        $plainId = 'nonexistent123';
        $encryptedId = $this->encryptionService->encrypt($plainId);

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($plainId)
            ->willReturn(null);

        $this->om->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\User')
            ->willReturn($repository);

        $request = new Request();
        $request->attributes->set('user', $encryptedId);

        $argument = new ArgumentMetadata(
            'user',
            'App\Entity\User',
            false,
            false,
            null,
            false,
            [new EncryptedId('App\Entity\User')]
        );

        $result = $this->resolver->resolve($request, $argument);
        iterator_to_array($result); // Force evaluation
    }

    public function testResolveWithRealParseObjectId(): void
    {
        // Simulate a real Parse object ID
        $plainId = 'a1b2c3d4e5f6g7h8i9j0';
        $encryptedId = $this->encryptionService->encrypt($plainId);

        $mockEntity = new \stdClass();
        $mockEntity->id = $plainId;

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($plainId)
            ->willReturn($mockEntity);

        $this->om->expects($this->once())
            ->method('getRepository')
            ->with('App\ParseObject\BlogPost')
            ->willReturn($repository);

        $request = new Request();
        $request->attributes->set('post', $encryptedId);

        $argument = new ArgumentMetadata(
            'post',
            'App\ParseObject\BlogPost',
            false,
            false,
            null,
            false,
            [new EncryptedId('App\ParseObject\BlogPost')]
        );

        $result = $this->resolver->resolve($request, $argument);
        $result = iterator_to_array($result);

        $this->assertCount(1, $result);
        $this->assertSame($mockEntity, $result[0]);
        $this->assertEquals($plainId, $result[0]->id);
    }
}
