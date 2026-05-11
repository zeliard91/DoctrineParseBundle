<?php

namespace Redking\ParseBundle\Tests\Functional;

use Doctrine\Common\EventArgs;
use Redking\ParseBundle\Event\LifecycleEventArgs;
use Redking\ParseBundle\Event\OnFlushEventArgs;
use Redking\ParseBundle\Events;
use Redking\ParseBundle\Tests\Models\Blog\Picture;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\User;

/**
 * Reproduces the Gedmo Loggable doublons reported in production:
 * a postUpdate listener that triggers $om->flush($otherObject) makes the UoW
 * re-dispatch onFlush during the nested commit. Listeners that iterate the
 * scheduled-update set (such as Loggable) then see the OUTER commit's
 * still-pending objects and process them a second time.
 *
 * The expected behavior is the same as Doctrine ORM: a flush nested inside a
 * postUpdate must not re-emit onFlush events. Listeners must observe a single
 * onFlush per top-level commit.
 */
class NestedFlushOnFlushTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        User::class,
        Post::class,
        Picture::class,
    ];

    public function testOnFlushDispatchedOnceAcrossNestedFlush(): void
    {
        $listener = new NestedFlushListener();
        $this->om->getEventManager()->addEventListener(
            [Events::onFlush, Events::postUpdate],
            $listener
        );

        $user = new User();
        $user->setName('Alice');
        $user->setPassword('p4ss');
        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $listener->onFlushCalls = 0;

        /** @var User $user */
        $user = $this->om->getRepository(User::class)->findOneBy(['name' => 'Alice']);
        $user->setName('Bob');

        $this->om->flush();

        self::assertSame(
            1,
            $listener->onFlushCalls,
            'onFlush must fire exactly once per top-level commit, even when a postUpdate listener triggers a nested flush.'
        );
    }

    /**
     * Guards against a regression where the commit-depth counter would not be
     * reset between top-level flushes: each subsequent flush() in the same
     * request must still dispatch onFlush.
     */
    public function testOnFlushIsRedispatchedOnSubsequentTopLevelFlushes(): void
    {
        $listener = new NestedFlushListener();
        $this->om->getEventManager()->addEventListener(
            [Events::onFlush, Events::postUpdate],
            $listener
        );

        // Initial persist + flush #1 (no nested flush — postUpdate is not fired on insert)
        $user = new User();
        $user->setName('Carol');
        $user->setPassword('p4ss');
        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        self::assertSame(1, $listener->onFlushCalls, 'onFlush must fire on the initial flush.');

        // Flush #2: update that triggers a nested flush from postUpdate
        /** @var User $user */
        $user = $this->om->getRepository(User::class)->findOneBy(['name' => 'Carol']);
        $user->setName('Bob');
        $this->om->flush();

        self::assertSame(2, $listener->onFlushCalls, 'onFlush must fire on flush #2 (nested flush must not skip it).');

        // Flush #3: a follow-up top-level update — proves commitDepth was reset after #2.
        $this->om->clear();
        $listener->resetNestedFlushGuard();

        $user = $this->om->getRepository(User::class)->findOneBy(['name' => 'Bob']);
        $user->setName('Dan');
        $this->om->flush();

        self::assertSame(3, $listener->onFlushCalls, 'onFlush must fire on flush #3 — commitDepth must have been decremented after flush #2.');
    }
}

class NestedFlushListener
{
    public int $onFlushCalls = 0;
    private bool $nestedFlushDone = false;

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->onFlushCalls++;
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        if ($this->nestedFlushDone) {
            return;
        }
        $object = $args->getObject();
        if (!$object instanceof User || $object->getName() !== 'Bob') {
            return;
        }
        $this->nestedFlushDone = true;

        $om = $args->getObjectManager();
        $picture = new Picture();
        $picture->setFile('triggered-by-post-update.jpg');
        $om->persist($picture);
        $om->flush($picture);
    }

    public function resetNestedFlushGuard(): void
    {
        $this->nestedFlushDone = false;
    }
}
