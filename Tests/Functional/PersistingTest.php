<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseQuery;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\Picture;

class PersistingTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\User',
        'Redking\ParseBundle\Tests\Models\Blog\Post',
        'Redking\ParseBundle\Tests\Models\Blog\Picture',
    ];

    public function testSave()
    {
        $user = new User();
        $user->setName('Foo');

        $this->om->persist($user);
        $this->om->flush();

        $collection = $this->om->getClassMetaData(User::class)->getCollection();
        $query = new ParseQuery($collection);
        $raw_user = $query->get($user->getId(), true);

        $this->assertInstanceOf('Parse\ParseObject', $raw_user);
        $this->assertEquals($user->getName(), $raw_user->get('name'));

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(0, $user->getPosts());
    }

    public function testSingleFlush()
    {
        $user1 = new User();
        $user1->setName('Foo');
        $this->om->persist($user1);

        $user2 = new User();
        $user2->setName('Bar');
        $this->om->persist($user2);

        $this->om->flush();

        $collection = $this->om->getClassMetaData(User::class)->getCollection();
        $query = new ParseQuery($collection);
        $users = $query->find(true);
        $this->assertCount(2, $users);

        $user1->setName('Foo Edited');
        $user2->setName('Bar Edited');

        // Only save the changes on one object.
        $this->om->flush($user1);

        $collection = $this->om->getClassMetaData(User::class)->getCollection();
        $query = new ParseQuery($collection);
        $users = $query->find(true);
        $this->assertCount(2, $users);
        $this->assertEquals($user1->getName(), $users[0]->get('name'));
        $this->assertEquals('Bar', $users[1]->get('name'));
    }

    public function testSaveWithNumericString()
    {
        $user = new User();
        $user->setName(31);

        $this->om->persist($user);
        $this->om->flush();

        $collection = $this->om->getClassMetaData(User::class)->getCollection();
        $query = new ParseQuery($collection);
        $raw_user = $query->get($user->getId(), true);

        $this->assertInstanceOf('Parse\ParseObject', $raw_user);
        $this->assertEquals($user->getName(), $raw_user->get('name'));

        $user = $this->om->getRepository(User::class)->findOneByName($raw_user->get('name'));
        $this->assertInstanceOf(User::class, $user);
    }

    public function testSaveWithoutCascade()
    {
        $user = new User();
        $user->setName('Foo');

        $post = new Post();
        $post->setText('lorem ipsum');
        $post->setUser($user);

        $this->om->persist($user);
        $this->om->persist($post);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(1, $user->getPosts());

    }

    public function testSaveWithCascade()
    {
        $user = new User();
        $user->setName('Foo');

        $picture = new Picture();
        $picture->setFile('dummy1.jpg');
        $user->addPicture($picture);
        $picture = new Picture();
        $picture->setFile('dummy2.jpg');
        $user->addPicture($picture);
        

        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(2, $user->getPictures());

    }

    
}