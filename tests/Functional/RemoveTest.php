<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseQuery;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\Picture;

class RemoveTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\User',
        'Redking\ParseBundle\Tests\Models\Blog\Post',
        'Redking\ParseBundle\Tests\Models\Blog\Picture',
    ];

    public function testRemove()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');

        $this->om->persist($user);
        $this->om->flush();
        $userId = $user->getId();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->find($userId);
        $this->assertNotNull($user);
        $this->om->remove($user);
        $this->om->flush();
        $this->om->clear();

        $collection = $this->om->getClassMetaData(User::class)->getCollection();
        $query = new ParseQuery($collection);
        $users = $query->equalTo('objectId', $userId)->find(true);

        $this->assertEmpty($users);
        
    }

    public function testRemoveWithoutCascade()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');

        $picture = new Picture();
        $picture->setFile('avatar.jpg');
        $user->setAvatar($picture);

        $this->om->persist($user);
        $this->om->persist($picture);
        $this->om->flush();
        $userId = $user->getId();
        $pictureId = $picture->getId();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->find($userId);
        $this->om->remove($user);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->find($userId);
        $this->assertNull($user);

        $picture = $this->om->getRepository(Picture::class)->find($pictureId);
        $this->assertInstanceOf(Picture::class, $picture);
    }

    public function testRemoveWithCascade()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');

        $picture = new Picture();
        $picture->setFile('dummy1.jpg');
        $user->addPicture($picture);
        $picture = new Picture();
        $picture->setFile('dummy2.jpg');
        $user->addPicture($picture);

        $picture = new Picture();
        $picture->setFile('dummy_solo.jpg');
        $this->om->persist($picture);
        

        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertCount(2, $user->getPictures());
        $this->om->remove($user);
        $this->om->flush();
        $this->om->clear();


        $pictures = $this->om->getRepository(Picture::class)->findAll();
        $this->assertCount(1, $pictures);
        $this->assertEquals('dummy_solo.jpg', $pictures[0]->getFile());
    }

    public function testRemoveOrphansOnMany()
    {
        $user = new User();
        $user->setPassword('p4ss');
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
        $user->removePicture($user->getPictures()->first());

        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertCount(1, $user->getPictures());
        $pictures = $this->om->getRepository(Picture::class)->findAll();
        $this->assertCount(1, $pictures);
    }

    public function testRemoveOrphansOnOne()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');

        $portrait = new Picture();
        $portrait->setFile('portrait.jpg');
        $user->setPortrait($portrait);

        $this->om->persist($portrait);
        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertNotNull($user->getPortrait());
        $user->setPortrait(null);

        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertNull($user->getPortrait());
        $pictures = $this->om->getRepository(Picture::class)->findAll();
        $this->assertCount(0, $pictures);
    }

    
}
