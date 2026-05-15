<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\CacheWarmer\DoctrineCacheWarmer;
use Redking\ParseBundle\CacheWarmer\ProxyCacheWarmer;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * CacheWarmerInterface declares isOptional(): bool and warmUp(string, ?string): array.
 * Missing those return types on the implementation triggers a fatal LSP error at
 * class-load on PHP 8.4+.
 */
class CacheWarmerSignatureTest extends TestCase
{
    public function testProxyCacheWarmerCanBeInstantiated(): void
    {
        $warmer = new ProxyCacheWarmer(new Container());

        $this->assertInstanceOf(CacheWarmerInterface::class, $warmer);
        $this->assertFalse($warmer->isOptional());
    }

    public function testDoctrineCacheWarmerCanBeInstantiated(): void
    {
        $warmer = new DoctrineCacheWarmer(new Container());

        $this->assertInstanceOf(CacheWarmerInterface::class, $warmer);
        $this->assertFalse($warmer->isOptional());
    }

    /**
     * @dataProvider warmerClassProvider
     */
    public function testWarmUpHasInterfaceCompatibleSignature(string $warmerClass): void
    {
        $method = new ReflectionMethod($warmerClass, 'warmUp');

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, $warmerClass . '::warmUp() must declare a return type');
        $this->assertSame('array', (string) $returnType);

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('cacheDir', $params[0]->getName());
        $this->assertSame('string', (string) $params[0]->getType());
        $this->assertSame('buildDir', $params[1]->getName());
        $this->assertTrue($params[1]->allowsNull());
    }

    /**
     * @return iterable<array{class-string}>
     */
    public static function warmerClassProvider(): iterable
    {
        yield 'ProxyCacheWarmer' => [ProxyCacheWarmer::class];
        yield 'DoctrineCacheWarmer' => [DoctrineCacheWarmer::class];
    }
}
