<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseQuery;
use Parse\ParseGeoPoint;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\Picture;

class QueryTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\User',
        'Redking\ParseBundle\Tests\Models\Blog\Post',
        'Redking\ParseBundle\Tests\Models\Blog\Picture',
    ];

    /**
     * @return QueryBuilder
     */
    private function getUserQB()
    {
        return $this->om->getRepository(User::class)
            ->createQueryBuilder();
    }

    public function testEquals()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $this->om->persist($user);
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Bar');
        $this->om->persist($user);

        $this->om->flush();

        $results = $this->getUserQB()
            ->field('name')->equals('Foo')
            ->getQuery()
            ->execute();
        ;

        $this->assertCount(1, $results);
        $this->assertEquals('Foo', $results[0]->getName());
    }

    public function testNotEquals()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $this->om->persist($user);
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Bar');
        $this->om->persist($user);

        $this->om->flush();

        $results = $this->getUserQB()
            ->field('name')->notEqual('Foo')
            ->getQuery()
            ->execute();
        ;

        $this->assertCount(1, $results);
        $this->assertEquals('Bar', $results[0]->getName());
    }

    public function testContains()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $this->om->persist($user);
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Bar');
        $this->om->persist($user);

        $this->om->flush();

        $results = $this->getUserQB()
            ->field('name')->contains('oo')
            ->getQuery()
            ->execute();
        ;

        $this->assertCount(1, $results);
        $this->assertEquals('Foo', $results[0]->getName());

        $results = $this->getUserQB()
            ->field('name')->contains('Oo')
            ->getQuery()
            ->execute();
        ;
        $this->assertCount(0, $results);
    }

    public function testRegex()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('FOO');
        $this->om->persist($user);
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Bar');
        $this->om->persist($user);

        $this->om->flush();

        $results = $this->getUserQB()
            ->field('name')->regex('\\QOO\\E')
            ->getQuery()
            ->execute();
        ;

        $this->assertCount(1, $results);
        $this->assertEquals('FOO', $results[0]->getName());

        $results = $this->getUserQB()
            ->field('name')->regex('\\Qoo\\E')
            ->getQuery()
            ->execute();
        ;
        $this->assertCount(0, $results);

        $results = $this->getUserQB()
            ->field('name')->regex('\\Qoo\\E', 'i')
            ->getQuery()
            ->execute();
        ;
        $this->assertCount(1, $results);
        $this->assertEquals('FOO', $results[0]->getName());

        $results = $this->getUserQB()
            ->field('name')->regex('^\\Qoo\\E', 'i')
            ->getQuery()
            ->execute();
        ;
        $this->assertCount(0, $results);

        $results = $this->getUserQB()
            ->field('name')->regex('\\Qoo\\E$', 'i')
            ->getQuery()
            ->execute();
        ;
        $this->assertCount(1, $results);
    }

    public function testOr()
    {
        $birthday1 = new \DateTime('1981-02-04T11:00:59.012000Z');
        $birthday2 = new \DateTime('2009-04-12T00:00:59.012000Z');

        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $user->setBirthday($birthday1);
        $this->om->persist($user);
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Bar');
        $user->setBirthday($birthday2);
        $this->om->persist($user);

        $this->om->flush();

        $qb = $this->getUserQB();
        $qb->addOr($qb->expr()->field('name')->equals('Nawak'));
        $qb->addOr($qb->expr()->field('birthday')->equals($birthday1));

        $results = $qb
            ->getQuery()
            ->execute();

        $this->assertCount(1, $results);
        $this->assertEquals('Foo', $results[0]->getName());
    }

    public function testReferenceId()
    {
        $avatar = new Picture();
        $avatar->setFile('test.jpg');
        $picture1 = new Picture();
        $picture1->setFile('picture1.jpg');
        $picture2 = new Picture();
        $picture2->setFile('picture2.jpg');
        $this->om->persist($avatar);
        $this->om->persist($picture1);
        $this->om->persist($picture2);
        $this->om->flush();

        $avatarId = $avatar->getId();
        $picture1Id = $picture1->getId();
        $picture2Id = $picture2->getId();

        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $user->setAvatar($avatar);
        $user->addPicture($picture1);
        $user->addPicture($picture2);

        $this->om->persist($user);
        $this->om->flush();

        $results = $this->getUserQB()
            ->field('avatar.id')->equals($avatarId)
            ->getQuery()
            ->execute()
        ;

        $this->assertCount(1, $results);
        $this->assertEquals('Foo', $results[0]->getName());

        $this->om->clear();

        $results = $this->getUserQB()
            ->field('pictures.id')->in([$picture2Id])
            ->getQuery()
            ->execute()
        ;

        $this->assertCount(1, $results);
        $this->assertEquals('Foo', $results[0]->getName());
    }

    public function testSort()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $this->om->persist($user);
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Bar');
        $this->om->persist($user);

        $this->om->flush();

        $results = $this->getUserQB()
            ->sort('name', 'asc')
            ->getQuery()
            ->execute()
        ;

        $this->assertCount(2, $results);
        $this->assertEquals('Bar', $results[0]->getName());

        $results = $this->getUserQB()
            ->sort('id', 'asc')
            ->getQuery()
            ->execute()
        ;
        $this->assertCount(2, $results);

    }

    public function testNear()
    {
        // Paris
        $userParis = new User();
        $userParis->setPassword('p4ss');
        $userParis->setName('Paris');
        $userParis->setLocation(new ParseGeoPoint(48.8566, 2.3522));
        $this->om->persist($userParis);

        // Lyon
        $userLyon = new User();
        $userLyon->setPassword('p4ss');
        $userLyon->setName('Lyon');
        $userLyon->setLocation(new ParseGeoPoint(45.7640, 4.8357));
        $this->om->persist($userLyon);

        // New York
        $userNY = new User();
        $userNY->setPassword('p4ss');
        $userNY->setName('NewYork');
        $userNY->setLocation(new ParseGeoPoint(40.7128, -74.0060));
        $this->om->persist($userNY);

        $this->om->flush();

        // Near Paris — results ordered by distance
        $results = $this->getUserQB()
            ->field('location')->near(new ParseGeoPoint(48.8566, 2.3522))
            ->getQuery()
            ->execute()
        ;

        $this->assertCount(3, $results);
        $this->assertEquals('Paris', $results[0]->getName());
    }

    public function testWithinKilometers()
    {
        // Paris
        $userParis = new User();
        $userParis->setPassword('p4ss');
        $userParis->setName('Paris');
        $userParis->setLocation(new ParseGeoPoint(48.8566, 2.3522));
        $this->om->persist($userParis);

        // Lyon (~391 km from Paris)
        $userLyon = new User();
        $userLyon->setPassword('p4ss');
        $userLyon->setName('Lyon');
        $userLyon->setLocation(new ParseGeoPoint(45.7640, 4.8357));
        $this->om->persist($userLyon);

        // New York (~5837 km from Paris)
        $userNY = new User();
        $userNY->setPassword('p4ss');
        $userNY->setName('NewYork');
        $userNY->setLocation(new ParseGeoPoint(40.7128, -74.0060));
        $this->om->persist($userNY);

        $this->om->flush();

        $results = $this->getUserQB()
            ->field('location')->withinKilometers(new ParseGeoPoint(48.8566, 2.3522), 500)
            ->getQuery()
            ->execute()
        ;

        $this->assertCount(2, $results);
        $names = array_map(fn($u) => $u->getName(), $results->toArray());
        $this->assertContains('Paris', $names);
        $this->assertContains('Lyon', $names);
        $this->assertNotContains('NewYork', $names);
    }

    public function testWithinMiles()
    {
        // Paris
        $userParis = new User();
        $userParis->setPassword('p4ss');
        $userParis->setName('Paris');
        $userParis->setLocation(new ParseGeoPoint(48.8566, 2.3522));
        $this->om->persist($userParis);

        // Lyon (~243 miles from Paris)
        $userLyon = new User();
        $userLyon->setPassword('p4ss');
        $userLyon->setName('Lyon');
        $userLyon->setLocation(new ParseGeoPoint(45.7640, 4.8357));
        $this->om->persist($userLyon);

        // New York (~3627 miles from Paris)
        $userNY = new User();
        $userNY->setPassword('p4ss');
        $userNY->setName('NewYork');
        $userNY->setLocation(new ParseGeoPoint(40.7128, -74.0060));
        $this->om->persist($userNY);

        $this->om->flush();

        $results = $this->getUserQB()
            ->field('location')->withinMiles(new ParseGeoPoint(48.8566, 2.3522), 300)
            ->getQuery()
            ->execute()
        ;

        $this->assertCount(2, $results);
        $names = array_map(fn($u) => $u->getName(), $results->toArray());
        $this->assertContains('Paris', $names);
        $this->assertContains('Lyon', $names);
        $this->assertNotContains('NewYork', $names);
    }

    public function testWithinGeoBox()
    {
        // Paris — inside box
        $userParis = new User();
        $userParis->setPassword('p4ss');
        $userParis->setName('Paris');
        $userParis->setLocation(new ParseGeoPoint(48.8566, 2.3522));
        $this->om->persist($userParis);

        // Lyon — inside box
        $userLyon = new User();
        $userLyon->setPassword('p4ss');
        $userLyon->setName('Lyon');
        $userLyon->setLocation(new ParseGeoPoint(45.7640, 4.8357));
        $this->om->persist($userLyon);

        // New York — outside box
        $userNY = new User();
        $userNY->setPassword('p4ss');
        $userNY->setName('NewYork');
        $userNY->setLocation(new ParseGeoPoint(40.7128, -74.0060));
        $this->om->persist($userNY);

        $this->om->flush();

        // Bounding box covering France roughly
        $southWest = new ParseGeoPoint(43.0, -5.0);
        $northEast = new ParseGeoPoint(51.0, 8.0);

        $results = $this->getUserQB()
            ->field('location')->withinGeoBox($southWest, $northEast)
            ->getQuery()
            ->execute()
        ;

        $this->assertCount(2, $results);
        $names = array_map(fn($u) => $u->getName(), $results->toArray());
        $this->assertContains('Paris', $names);
        $this->assertContains('Lyon', $names);
        $this->assertNotContains('NewYork', $names);
    }

}
