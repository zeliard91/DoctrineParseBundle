<?php

namespace Redking\ParseBundle\Tests\Functional\Events;

use Parse\ParseGeoPoint;
use Redking\ParseBundle\Event\PreUpdateEventArgs;
use Redking\ParseBundle\Events;
use Redking\ParseBundle\Tests\Models\Blog\Picture;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\TestCase;

class PreUpdateEventTest extends TestCase
{
    protected static $modelSets = [
        User::class,
        Post::class,
        Picture::class,
    ];

    public function testChangeSetIsUpdated(): void
    {
        $this->om->getEventManager()->addEventListener(Events::preUpdate, new ChangeSetIsUpdatedListener());

        $post = new Post();
        $post->setText('Initial');
        $this->om->persist($post);
        $this->om->flush();

        $post->setText('My Text');
        $this->om->flush();
        $this->om->clear();

        $post = $this->om->find(Post::class, $post->getId());
        self::assertEquals('Changed', $post->getText());
    }

    public function testInPointerIsUpdated(): void
    {
        $this->om->getEventManager()->addEventListener(Events::preUpdate, new ChangeSetIsUpdatedListener());

        $file = new Picture();
        $file->setFile('test.jpg');
        $this->om->persist($file);
        $this->om->flush();

        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        
        $file->setLocation(new ParseGeoPoint(0,0));
        $user->setAvatar($file);

        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        /**
         * @var User $user
         */
        $user = $this->om->find(User::class, $user->getId());
        self::assertInstanceOf(Picture::class, $user->getAvatar());
        self::assertEquals('Changed', $user->getAvatar()->getFile());
    }

    public function testInCollectionIsUpdated(): void
    {
        $this->om->getEventManager()->addEventListener(Events::preUpdate, new ChangeSetIsUpdatedListener());

        $post = new Post();
        $post->setText('Initial');
        $this->om->persist($post);
        $this->om->flush();

        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');

        $post->setText('My Text');
        $user->addPost($post);

        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();
        
        /**
         * @var User $user
         */
        $user = $this->om->find(User::class, $user->getId());
        self::assertEquals(1, $user->getPosts()->count());
        self::assertEquals('Changed', $user->getPosts()->first()->getText());
    }
}

class ChangeSetIsUpdatedListener
{
    public function preUpdate(PreUpdateEventArgs $e): void
    {
        $obj = $e->getObject();

        if ($obj instanceof Post) {
            $obj->setText('Changed');
        }

        if ($obj instanceof Picture) {
            $obj->setFile('Changed');
        }
    }
}