<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseQuery;
use Redking\ParseBundle\Tests\Models\Blog\User;

class PersistingTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\User',
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
    }

    
}