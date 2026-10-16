<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Tests\DependencyInjection;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Bridge\DataFixtures\ContainerAwareLoader;
use Redking\ParseBundle\DependencyInjection\RedkingParseExtension;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Symfony ContainerAwareLoader is gone in Symfony 7: the bundle ships its own
 * loader and exposes it under the Doctrine Loader type for autowiring.
 */
class FixtureLoaderWiringTest extends TestCase
{
    public function testDefaultsToBundleLoaderAutowiredAsDoctrineLoader(): void
    {
        $container = $this->loadExtension([]);

        $this->assertSame(ContainerAwareLoader::class, $container->getDefinition('doctrine_parse.fixture_loader')->getClass());
        $this->assertSame('doctrine_parse.fixture_loader', (string) $container->getAlias(Loader::class));
        $this->assertSame('doctrine_parse.fixture_loader', (string) $container->getAlias(ContainerAwareLoader::class));
    }

    public function testAcceptsAnyDoctrineLoaderSubclass(): void
    {
        $container = $this->loadExtension(['fixture_loader' => CustomFixtureLoaderStub::class]);

        $this->assertSame(CustomFixtureLoaderStub::class, $container->getDefinition('doctrine_parse.fixture_loader')->getClass());
        $this->assertSame('doctrine_parse.fixture_loader', (string) $container->getAlias(Loader::class));
    }

    public function testRejectsClassNotExtendingDoctrineLoader(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\stdClass::class);

        $this->loadExtension(['fixture_loader' => \stdClass::class]);
    }

    public function testLoaderRegistersFixtures(): void
    {
        $loader = new ContainerAwareLoader(new ContainerBuilder());
        $fixture = new PlainFixtureStub();

        $loader->addFixture($fixture);

        $this->assertSame([PlainFixtureStub::class => $fixture], $loader->getFixtures());
    }

    public function testLoaderInjectsContainerIntoContainerAwareFixtures(): void
    {
        if (!interface_exists(ContainerAwareInterface::class)) {
            $this->markTestSkipped('ContainerAwareInterface was removed in Symfony 7.');
        }

        $container = new ContainerBuilder();
        $fixture = new class() implements FixtureInterface, ContainerAwareInterface {
            public ?ContainerInterface $container = null;

            public function setContainer(?ContainerInterface $container = null): void
            {
                $this->container = $container;
            }

            public function load(ObjectManager $manager): void
            {
            }
        };

        (new ContainerAwareLoader($container))->addFixture($fixture);

        $this->assertSame($container, $fixture->container);
    }

    private function loadExtension(array $extraConfig): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);

        $extension = new RedkingParseExtension();
        $extension->load([
            array_merge([
                'app_id'     => 'a',
                'rest_key'   => 'b',
                'master_key' => 'c',
                'server_url' => 'http://localhost:1337',
            ], $extraConfig),
        ], $container);

        return $container;
    }
}

class CustomFixtureLoaderStub extends Loader
{
    public function __construct(ContainerInterface $container)
    {
    }
}

class PlainFixtureStub implements FixtureInterface
{
    public function load(ObjectManager $manager): void
    {
    }
}
