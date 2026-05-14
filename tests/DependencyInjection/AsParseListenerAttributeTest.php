<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Attribute\AsParseListener;
use Redking\ParseBundle\DependencyInjection\RedkingParseExtension;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AsParseListenerAttributeTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function minimalConfig(): array
    {
        return [
            'app_id'     => 'test_app_id',
            'rest_key'   => 'test_rest_key',
            'master_key' => 'test_master_key',
            'server_url' => 'http://localhost:1337',
            'mount_path' => 'parse',
        ];
    }

    public function testAutoconfiguratorIsRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);

        $extension = new RedkingParseExtension();
        $extension->load([$this->minimalConfig()], $container);

        $configurators = $container->getAutoconfiguredAttributes();
        $this->assertArrayHasKey(AsParseListener::class, $configurators);
    }

    public function testAutoconfiguratorEmitsEventListenerTag(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);

        $extension = new RedkingParseExtension();
        $extension->load([$this->minimalConfig()], $container);

        $configurator = $container->getAutoconfiguredAttributes()[AsParseListener::class];

        $definition = new ChildDefinition('stdClass');
        $configurator($definition, new AsParseListener(event: 'prePersist', priority: 10));

        $this->assertSame(
            [['event' => 'prePersist', 'priority' => 10]],
            $definition->getTag('doctrine_parse.event_listener'),
        );
    }

    public function testAutoconfiguratorIncludesConnectionWhenProvided(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);

        $extension = new RedkingParseExtension();
        $extension->load([$this->minimalConfig()], $container);

        $configurator = $container->getAutoconfiguredAttributes()[AsParseListener::class];

        $definition = new ChildDefinition('stdClass');
        $configurator($definition, new AsParseListener(event: 'postUpdate', connection: 'redking_parse.manager'));

        $this->assertSame(
            [['event' => 'postUpdate', 'connection' => 'redking_parse.manager']],
            $definition->getTag('doctrine_parse.event_listener'),
        );
    }

    public function testAutoconfiguratorOmitsOptionalKeysWhenNull(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);

        $extension = new RedkingParseExtension();
        $extension->load([$this->minimalConfig()], $container);

        $configurator = $container->getAutoconfiguredAttributes()[AsParseListener::class];

        $definition = new ChildDefinition('stdClass');
        $configurator($definition, new AsParseListener(event: 'prePersist'));

        $this->assertSame(
            [['event' => 'prePersist']],
            $definition->getTag('doctrine_parse.event_listener'),
        );
    }
}
