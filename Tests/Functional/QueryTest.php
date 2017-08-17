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
        $user->setName('Foo');
        $this->om->persist($user);
        $user = new User();
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
        $user->setName('Foo');
        $this->om->persist($user);
        $user = new User();
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
        $user->setName('Foo');
        $this->om->persist($user);
        $user = new User();
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
        $user->setName('FOO');
        $this->om->persist($user);
        $user = new User();
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
        $user->setName('Foo');
        $user->setBirthday($birthday1);
        $this->om->persist($user);
        $user = new User();
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
        $this->om->persist($avatar);
        $this->om->flush();

        $avatarId = $avatar->getId();

        $user = new User();
        $user->setName('Foo');
        $user->setAvatar($avatar);

        $this->om->persist($user);
        $this->om->flush();

        $results = $this->getUserQB()
            ->field('avatar.id')->equals($avatarId)
            ->getQuery()
            ->execute()
        ;

        $this->assertCount(1, $results);
        $this->assertEquals('Foo', $results[0]->getName());
    }

}