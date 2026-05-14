<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\DependencyInjection\RedkingParseExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolveTargetObjectListenerWiringTest extends TestCase
{
    public function testResolveTargetObjectsRegistersTwoEventListenerTags(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);

        $extension = new RedkingParseExtension();
        $extension->load([
            [
                'app_id'     => 'a',
                'rest_key'   => 'b',
                'master_key' => 'c',
                'server_url' => 'http://localhost:1337',
                'resolve_target_objects' => [
                    'App\\Foo' => 'App\\Bar',
                ],
            ],
        ], $container);

        $definition = $container->findDefinition('doctrine.parse.listeners.resolve_target_object');

        $this->assertSame(
            [
                ['event' => 'loadClassMetadata'],
                ['event' => 'onClassMetadataNotFound'],
            ],
            $definition->getTag('doctrine_parse.event_listener'),
        );

        $this->assertSame([], $definition->getTag('doctrine_parse.event_subscriber'));
    }
}
