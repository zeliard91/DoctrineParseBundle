<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseQuery;
use Redking\ParseBundle\Tests\Models\Blog\Address;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\Picture;

class RelationTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\Address',
        'Redking\ParseBundle\Tests\Models\Blog\User',
        'Redking\ParseBundle\Tests\Models\Blog\Post',
        'Redking\ParseBundle\Tests\Models\Blog\Picture',
    ];

    public function testSaveRelation()
    {
        $adress = new Address();
        $adress->setCity('Paris');
        $this->om->persist($adress);
        $adress2 = new Address();
        $adress2->setCity('Tokyo');
        $this->om->persist($adress2);
        $this->om->flush();

        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $user->addAddress($adress);
        $user->addAddress($adress2);
        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertNotNull($user);
        $this->assertCount(2, $user->getAddresses());
    }

    public function testSaveRelationInCascade()
    {
        $adress = new Address();
        $adress->setCity('Paris');
        $this->om->persist($adress);
        $this->om->flush();
        $adress2 = new Address();
        $adress2->setCity('Tokyo');
        $adress3 = new Address();
        $adress3->setCity('London');
        
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $user->addAddress($adress);
        $user->addAddress($adress2);
        $user->addAddress($adress3);
        $this->om->persist($user);

        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertNotNull($user);
        $this->assertCount(3, $user->getAddresses());
    }

    public function testEditRelation()
    {
        $adress = new Address();
        $adress->setCity('Paris');
        $adress2 = new Address();
        $adress2->setCity('Tokyo');
        $adress3 = new Address();
        $adress3->setCity('London');
        
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $user->addAddress($adress);
        $user->addAddress($adress2);
        $user->addAddress($adress3);
        $this->om->persist($user);

        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertNotNull($user);
        $this->assertCount(3, $user->getAddresses());

        $user->removeAddress($user->getAddressByCity('Paris'));
        $user->removeAddress($user->getAddressByCity('London'));
        $adress4 = new Address();
        $adress4->setCity('Berlin');
        $user->addAddress($adress4);

        $this->om->persist($user);

        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertNotNull($user);
        $this->assertCount(2, $user->getAddresses());

        $cities = [];
        foreach ($user->getAddresses() as $address) {
            $cities[] = $address->getCity();
        }

        $this->assertEquals(['Tokyo', 'Berlin'], $cities, 'Test cities', 0.0, 10, true);
    }

    public function testInversedSide()
    {
        $adress = new Address();
        $adress->setCity('Paris');
        $adress2 = new Address();
        $adress2->setCity('Tokyo');
        $adress3 = new Address();
        $adress3->setCity('London');
        
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $user->addAddress($adress);
        $user->addAddress($adress2);
        $user->addAddress($adress3);
        $this->om->persist($user);

        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Bar');
        $user->addAddress($adress);
        $this->om->persist($user);

        $this->om->flush();
        $this->om->clear();

        $adress = $this->om->getRepository(Address::class)->findOneByCity('Paris');
        $this->assertNotNull($adress);
        $this->assertCount(2, $adress->getUsers());
    }

    public function testUpdateAfterClear()
    {
        $adress = new Address();
        $adress->setCity('Paris');
        $this->om->persist($adress);
        $adress2 = new Address();
        $adress2->setCity('Tokyo');
        $this->om->persist($adress2);
        $adress3 = new Address();
        $adress3->setCity('London');
        $this->om->persist($adress3);

        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('Foo');
        $user->addAddress($adress);
        $user->addAddress($adress2);
        $this->om->persist($user);

        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertNotNull($user);
        $this->assertCount(2, $user->getAddresses());

        $user->getAddresses()->clear();
        $this->assertCount(0, $user->getAddresses());

        $address4 = $this->om->getRepository(Address::class)->findOneByCity('Tokyo');
        $this->assertNotNull($address4);
        $user->addAddress($address4);
        $address5 = $this->om->getRepository(Address::class)->findOneByCity('London');
        $this->assertNotNull($address5);
        $user->addAddress($address5);

        $this->assertCount(2, $user->getAddresses());
        $cities = [];
        foreach ($user->getAddresses() as $address) {
            $cities[] = $address->getCity();
        }
        $this->assertEquals(['Tokyo', 'London'], $cities, 'Test cities', 0.0, 10, true);

        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertNotNull($user);
        $this->assertCount(2, $user->getAddresses());

        $cities = [];
        foreach ($user->getAddresses() as $address) {
            $cities[] = $address->getCity();
        }
        $this->assertEquals(['Tokyo', 'London'], $cities, 'Test cities', 0.0, 10, true);
    }
}
