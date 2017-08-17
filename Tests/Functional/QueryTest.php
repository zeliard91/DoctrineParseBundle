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

}