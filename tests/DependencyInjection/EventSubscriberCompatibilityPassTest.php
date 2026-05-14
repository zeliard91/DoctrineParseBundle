<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Tests\DependencyInjection;

use Doctrine\Common\EventSubscriber;
use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\DependencyInjection\Compiler\EventSubscriberCompatibilityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class LegacyMultiEventSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return ['prePersist', 'postPersist'];
    }
}

class LegacySingleEventSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return ['loadClassMetadata'];
    }
}

class LegacySubscriberWithRequiredConstructor implements EventSubscriber
{
    public function __construct(string $required)
    {
    }

    public function getSubscribedEvents(): array
    {
        return ['preFlush'];
    }
}

/**
 * @group legacy
 */
class EventSubscriberCompatibilityPassTest extends TestCase
{
    public function testConvertsMultiEventSubscriberTagsIntoListenerTags(): void
    {
        $container = new ContainerBuilder();

        $definition = new Definition(LegacyMultiEventSubscriber::class);
        $definition->addTag('doctrine_parse.event_subscriber');
        $container->setDefinition('app.legacy_subscriber', $definition);

        (new EventSubscriberCompatibilityPass())->process($container);

        $listenerTags = $definition->getTag('doctrine_parse.event_listener');
        $this->assertSame(
            [['event' => 'prePersist'], ['event' => 'postPersist']],
            $listenerTags,
        );
        $this->assertSame([], $definition->getTag('doctrine_parse.event_subscriber'));
    }

    public function testConvertsSingleEventSubscriberTag(): void
    {
        $container = new ContainerBuilder();

        $definition = new Definition(LegacySingleEventSubscriber::class);
        $definition->addTag('doctrine_parse.event_subscriber');
        $container->setDefinition('app.legacy_subscriber', $definition);

        (new EventSubscriberCompatibilityPass())->process($container);

        $this->assertSame(
            [['event' => 'loadClassMetadata']],
            $definition->getTag('doctrine_parse.event_listener'),
        );
    }

    public function testInstantiatesWithoutConstructorSoSubscriberDependenciesAreIgnored(): void
    {
        $container = new ContainerBuilder();

        $definition = new Definition(LegacySubscriberWithRequiredConstructor::class);
        $definition->addTag('doctrine_parse.event_subscriber');
        $container->setDefinition('app.legacy_subscriber', $definition);

        (new EventSubscriberCompatibilityPass())->process($container);

        $this->assertSame(
            [['event' => 'preFlush']],
            $definition->getTag('doctrine_parse.event_listener'),
        );
    }
}
