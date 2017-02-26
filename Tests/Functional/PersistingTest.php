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
        $raw_user = $query->get($user->getId());

        $this->assertInstanceOf('Parse\ParseObject', $raw_user);
        $this->assertEquals($user->getName(), $raw_user->get('name'));

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(0, $user->getPosts());
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