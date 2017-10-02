<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseQuery;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\Picture;

class CollectionTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\User',
        'Redking\ParseBundle\Tests\Models\Blog\Post',
        'Redking\ParseBundle\Tests\Models\Blog\Picture',
    ];

    public function testAddCollection()
    {
        $user = new User();
        $user->setName('Foo');

        $this->om->persist($user);
        $this->om->flush();
        
        $screen1 = new Picture();
        $screen1->setFile('screen1.jpg');
        $screen2 = new Picture();
        $screen2->setFile('screen2.jpg');
        $this->om->persist($screen1);
        $this->om->persist($screen2);
        $this->om->flush();

        $user->addScreenshot($screen1);
        $user->addScreenshot($screen2);
        $this->om->flush();
        
        $this->assertCount(2, $user->getScreenshots());

        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertCount(2, $user->getScreenshots());
    }

    public function testClearCollection()
    {
        $user = new User();
        $user->setName('Foo');

        $this->om->persist($user);
        $this->om->flush();
        
        $screen1 = new Picture();
        $screen1->setFile('screen1.jpg');
        $screen2 = new Picture();
        $screen2->setFile('screen2.jpg');
        $this->om->persist($screen1);
        $this->om->persist($screen2);
        $this->om->flush();

        $user->addScreenshot($screen1);
        $user->addScreenshot($screen2);
        $this->om->flush();
        
        $this->assertCount(2, $user->getScreenshots());
        $this->om->clear();


        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $user->getScreenshots()->clear();
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('Foo');
        $this->assertCount(0, $user->getScreenshots());
    }
    
}
