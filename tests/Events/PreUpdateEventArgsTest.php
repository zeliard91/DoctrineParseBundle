<?php

namespace Redking\ParseBundle\Tests\Events;

use Redking\ParseBundle\Event\PreUpdateEventArgs;
use Redking\ParseBundle\Events;
use Redking\ParseBundle\Tests\Models\Blog\Address;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\Picture;
use Redking\ParseBundle\Tests\TestCase;

class PreUpdateEventArgsTest extends TestCase
{
    protected static $modelSets = [
        User::class,
        Picture::class,
    ];

    public function testCollectionIsUpdated(): void
    {
        $this->om->getEventManager()->addEventListener(Events::preUpdate, new AddElementInCollectionListener());

        $picture = (new Picture())
            ->setFile('foo.pdf')
        ;
        $user = (new User())
            ->setName('Foo')
            ->setPassword('Bar')
            ->setAvatar($picture)
        ;
        $this->om->persist($picture);
        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->find(User::class, $user->getId());
        $this->assertEmpty($user->getPictures(), 'Check that collection is empty');

        $user->setName('Foo - edited');
        $this->om->flush();
        $this->om->clear();
        $this->assertEquals($user->getAvatar(), $user->getPictures()->first(), 'Check that element has been added by event listener');
        $this->assertEquals($user->getAvatar(), $user->getPortrait(), 'Check that element has been copied by event listener');

        $user = $this->om->find(User::class, $user->getId());
        $this->assertEquals($user->getAvatar(), $user->getPictures()->first(), 'Check that element has been added by event listener and is present after reload');
        $this->assertEquals($user->getAvatar(), $user->getPortrait(), 'Check that element has been copied by event listener and is present after reload');
    }
}


class AddElementInCollectionListener
{
    public function preUpdate(PreUpdateEventArgs $e): void
    {
        $object = $e->getObject();
        // $uow = $e->getObjectManager()->getUnitOfWork();
        if ($object instanceof User) {
            if ($object->getPictures()->count() === 0 && !empty($object->getAvatar())) {
                $object->getPictures()->add($object->getAvatar());
            }
            if (null === $object->getPortrait() && !empty($object->getAvatar())) {
                $object->setPortrait($object->getAvatar());
            }
        }
    }
}