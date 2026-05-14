<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Tests\Events;

use ReflectionClass;
use Redking\ParseBundle\Attribute\AsParseListener;
use Redking\ParseBundle\Event\LifecycleEventArgs;
use Redking\ParseBundle\Events;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\TestCase;

class AsParseListenerFunctionalTest extends TestCase
{
    protected static $modelSets = [
        User::class,
    ];

    public function testListenerClassCarriesAsParseListenerAttribute(): void
    {
        $attributes = (new ReflectionClass(PostPersistAttributeListener::class))
            ->getAttributes(AsParseListener::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame(Events::postPersist, $instance->event);
    }

    public function testListenerIsInvokedWhenRegisteredOnEventManager(): void
    {
        $listener = new PostPersistAttributeListener();
        $this->om->getEventManager()->addEventListener(Events::postPersist, $listener);

        $user = (new User())
            ->setName('Listener-Test')
            ->setPassword('secret')
        ;
        $this->om->persist($user);
        $this->om->flush();

        $this->assertTrue($listener->wasInvoked, 'postPersist listener should have been invoked');
        $this->assertSame($user, $listener->lastObject);
    }
}

#[AsParseListener(event: Events::postPersist)]
class PostPersistAttributeListener
{
    public bool $wasInvoked = false;

    public ?object $lastObject = null;

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->wasInvoked = true;
        $this->lastObject = $args->getObject();
    }
}
