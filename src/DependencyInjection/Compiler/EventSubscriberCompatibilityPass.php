<?php

declare(strict_types=1);

namespace Redking\ParseBundle\DependencyInjection\Compiler;

use Doctrine\Common\EventSubscriber;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

use function class_exists;
use function is_int;
use function is_string;
use function sprintf;
use function trigger_deprecation;

/**
 * Converts legacy Doctrine Parse subscribers (tag "doctrine_parse.event_subscriber"
 * or services implementing Doctrine\Common\EventSubscriber) into per-event
 * "doctrine_parse.event_listener" tags so they keep working with Symfony's
 * RegisterEventListenersAndSubscribersPass which no longer reads subscriber tags.
 *
 * @internal
 */
final class EventSubscriberCompatibilityPass implements CompilerPassInterface
{
    private const SUBSCRIBER_TAG = 'doctrine_parse.event_subscriber';
    private const LISTENER_TAG   = 'doctrine_parse.event_listener';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds(self::SUBSCRIBER_TAG, true) as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class      = $container->getParameterBag()->resolveValue($definition->getClass());

            if (!is_string($class) || !class_exists($class)) {
                throw new InvalidArgumentException(sprintf('Service "%s" tagged "%s" has no resolvable class.', $serviceId, self::SUBSCRIBER_TAG));
            }

            if (!is_subclass_of($class, EventSubscriber::class)) {
                throw new InvalidArgumentException(sprintf('Service "%s" tagged "%s" must implement "%s".', $serviceId, self::SUBSCRIBER_TAG, EventSubscriber::class));
            }

            $reflection = new ReflectionClass($class);
            /** @var EventSubscriber $instance */
            $instance = $reflection->newInstanceWithoutConstructor();

            foreach ($instance->getSubscribedEvents() as $key => $value) {
                $event = is_int($key) ? $value : $key;

                foreach ($tags as $tagAttributes) {
                    $listenerTag = ['event' => $event] + $tagAttributes;
                    unset($listenerTag['name']);
                    $definition->addTag(self::LISTENER_TAG, $listenerTag);
                }
            }

            $definition->clearTag(self::SUBSCRIBER_TAG);

            trigger_deprecation(
                'redking/doctrine-parse-bundle',
                '2.x',
                'Registering "%s" as a Doctrine Parse subscriber is deprecated. Use the #[%s] attribute or the "%s" tag instead.',
                $class,
                \Redking\ParseBundle\Attribute\AsParseListener::class,
                self::LISTENER_TAG,
            );
        }
    }
}
