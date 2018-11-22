<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseQuery;
use Parse\ParseGeoPoint;
use Redking\ParseBundle\Tests\Models\Blog\Address;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\Picture;

class PersistingTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\User',
        'Redking\ParseBundle\Tests\Models\Blog\Post',
        'Redking\ParseBundle\Tests\Models\Blog\Picture',
        'Redking\ParseBundle\Tests\Models\Blog\Address',
    ];

    public function testSave()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $birthday = new \DateTime('1981-02-04T11:00:59.012000Z');
        $user->setBirthday($birthday);

        $this->om->persist($user);
        $this->om->flush();

        $this->assertNotFalse($this->om->getUnitOfWork()->tryGetById($user->getId(), User::class));

        $collection = $this->om->getClassMetaData(User::class)->getCollection();
        $query = new ParseQuery($collection);
        $raw_user = $query->get($user->getId(), true);

        $this->assertInstanceOf('Parse\ParseObject', $raw_user);
        $this->assertEquals($user->getName(), $raw_user->get('username'));
        $this->assertEquals($user->getBirthday(), $raw_user->get('birthday'));

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(0, $user->getPosts());
    }

    public function testSingleFlush()
    {
        $user1 = new User();
        $user1->setPassword('p4ss');
        $user1->setName('Foo');
        $this->om->persist($user1);

        $user2 = new User();
        $user2->setPassword('p4ss');
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
        $users = $query->descending('username')->find(true);
        $this->assertCount(2, $users);
        $this->assertEquals($user1->getName(), $users[0]->get('username'));
        $this->assertEquals('Bar', $users[1]->get('username'));

        // Test if users are in identityMap after update
        $this->assertTrue($this->om->getUnitOfWork()->isInIdentityMap($user1));
        $this->assertNotFalse($this->om->getUnitOfWork()->tryGetById($user1->getId(), User::class));
        $this->assertTrue($this->om->getUnitOfWork()->isInIdentityMap($user2));
        $this->assertNotFalse($this->om->getUnitOfWork()->tryGetById($user2->getId(), User::class));
    }

    public function testSaveWithNumericString()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName(31);

        $this->om->persist($user);
        $this->om->flush();

        $collection = $this->om->getClassMetaData(User::class)->getCollection();
        $query = new ParseQuery($collection);
        $raw_user = $query->get($user->getId(), true);

        $this->assertInstanceOf('Parse\ParseObject', $raw_user);
        $this->assertEquals($user->getName(), $raw_user->get('username'));

        $user = $this->om->getRepository(User::class)->findOneByName($raw_user->get('username'));
        $this->assertInstanceOf(User::class, $user);
    }

    public function testSaveWithoutCascade()
    {
        $user = new User();
        $user->setPassword('p4ss');
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

    public function testSaveWithUpdatedLinkedObject()
    {
        $avatar = new Picture();
        $avatar->setFile('dummy.jpg');

        $this->om->persist($avatar);
        $this->om->flush();
        $this->om->clear();

        $avatar = $this->om->getRepository(Picture::class)->findOneByFile('dummy.jpg');
        $this->assertNotNull($avatar);

        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $user->setAvatar($avatar);
        $avatar->setFile('dummy2.jpg');

        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('dummy2.jpg', $user->getAvatar()->getFile());

    }

    public function testSaveWithCascade()
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
        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(2, $user->getPictures());

    }

    public function testSaveGeoPoint()
    {
        $picture = new Picture();
        $picture->setFile('Test');
        $picture->setLocation(new ParseGeoPoint(40.123456789, -50));

        $this->om->persist($picture);
        $this->om->flush();
        $this->om->clear();

        $picture = $this->om->getRepository(Picture::class)->findOneByFile('Test');
        $this->assertInstanceOf(Picture::class, $picture);
        $this->assertInstanceOf(ParseGeoPoint::class, $picture->getLocation());
        $this->assertEquals(40.123456789, $picture->getLocation()->getLatitude());
        $this->assertEquals(-50, $picture->getLocation()->getLongitude());

        // Test update
        $picture->getLocation()->setLatitude(66.6666);
        $this->om->persist($picture);
        $this->om->flush();
        $this->om->clear();

        $picture = $this->om->getRepository(Picture::class)->findOneByFile('Test');
        $this->assertEquals(66.6666, $picture->getLocation()->getLatitude());

    }

    public function testSavedObjectField()
    {
        $picture = new Picture();
        $picture->setFile('Test');
        $exif = [
            'location' => [
                'city' => 'Marigot',
                'zipCode' => 97150
            ],
            'resolution' => [
                'width' => 1920,
                'height' => 1080,
            ]
        ];
        $picture->setExif($exif);

        $this->om->persist($picture);
        $this->om->flush();
        $this->om->clear();

        $picture = $this->om->getRepository(Picture::class)->findOneByFile('Test');
        $this->assertInstanceOf(Picture::class, $picture);
        $this->assertEquals($exif, $picture->getExif());

    }

    public function testSavingBoolean()
    {
        $adress = new Address();
        $adress->setCity('Paris');

        $this->om->persist($adress);
        $this->om->flush();
        $this->om->clear();

        $adress = $this->om->getRepository(Address::class)->findOneByCity('Paris');
        $this->assertNotNull($adress);
        $this->assertFalse($adress->getIsDefault());

        $adress->setIsDefault(true);
        $this->om->flush();
        $this->om->clear();

        $adress = $this->om->getRepository(Address::class)->findOneByCity('Paris');
        $this->assertTrue($adress->getIsDefault());
        $this->om->clear();



        $adress = new Address();
        $adress->setCity('New York');
        $adress->setIsDefault(true);
        $this->om->persist($adress);
        $this->om->flush();
        $this->om->clear();

        $adress = $this->om->getRepository(Address::class)->findOneByCity('New York');
        $this->assertNotNull($adress);
        $this->assertTrue($adress->getIsDefault());

        $adress->setIsDefault(false);
        $this->om->persist($adress);
        $this->om->flush();
        $this->om->clear();

        $adress = $this->om->getRepository(Address::class)->findOneByCity('New York');
        $this->assertNotNull($adress);
        $this->assertFalse($adress->getIsDefault());
    }

    public function testSaveNumber()
    {
        $adress = new Address();
        $adress->setCity('Paris');

        $this->om->persist($adress);
        $this->om->flush();
        $this->om->clear();

        $adress = $this->om->getRepository(Address::class)->findOneByCity('Paris');
        $this->assertNull($adress->getOrder());

        $adress->setOrder(0);
        $this->om->persist($adress);
        $this->om->flush();
        $this->om->clear();

        $adress = $this->om->getRepository(Address::class)->findOneByCity('Paris');
        $this->assertEquals(0, $adress->getOrder());

        $adress = new Address();
        $adress->setCity('New York');
        $adress->setOrder(5);

        $this->om->persist($adress);
        $this->om->flush();
        $this->om->clear();

        $adress = $this->om->getRepository(Address::class)->findOneByCity('New York');
        $this->assertEquals(5, $adress->getOrder());

        $adress->setOrder(0);
        $this->om->persist($adress);
        $this->om->flush();
        $this->om->clear();

        $adress = $this->om->getRepository(Address::class)->findOneByCity('New York');
        $this->assertEquals(0, $adress->getOrder());

        $adress->setOrder(null);
        $this->om->persist($adress);
        $this->om->flush();
        $this->om->clear();

        $adress = $this->om->getRepository(Address::class)->findOneByCity('New York');
        $this->assertNull($adress->getOrder());
    }

    public function testSaveZonedDates()
    {
        $date = new \DateTime('1981-02-04T11:00:59.012000Z');
        $birthday = clone $date;
        $birthday->setTimezone(new \DateTimeZone('America/St_Barthelemy'));
        // Should be store in parse in UTC

        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('foo');
        $user->setBirthday($birthday);

        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $dateZoned = clone $birthday;

        // Check that we can refind it with a zoned date
        $user = $this->om->getRepository(User::class)->findOneByBirthday($dateZoned);
        $this->assertNotNull($user);
        $this->assertNotNull($user->getBirthday());
        $this->assertEquals($date->format('U'), $user->getBirthday()->format('U'));

        // Check that we can refind it with a UTC date
        $user = $this->om->getRepository(User::class)->findOneByBirthday($date);
        $this->assertNotNull($user);
        $this->assertNotNull($user->getBirthday());
        $this->assertEquals($date->format('U'), $user->getBirthday()->format('U'));

        // Check that if we change the timezone, no update is scheduled
        $user->getBirthday()->setTimezone(new \DateTimeZone('Europe/Paris'));
        $this->om->getUnitOfWork()->recomputeSingleObjectChangeSet($this->om->getClassMetaData(User::class, $user), $user);
        $changes = $this->om->getUnitOfWork()->getObjectChangeSet($user);
        $this->assertFalse(isset($changes['birthday']));
        
        // Add one minute, an update must be scheduled 
        $user->getBirthday()->add(new \DateInterval('PT1M'));
        $this->om->getUnitOfWork()->recomputeSingleObjectChangeSet($this->om->getClassMetaData(User::class, $user), $user);
        $changes = $this->om->getUnitOfWork()->getObjectChangeSet($user);
        $this->assertTrue(isset($changes['birthday']));
    }
}
