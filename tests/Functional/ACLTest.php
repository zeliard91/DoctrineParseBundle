<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseQuery;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\Models\Blog\Picture;
use Redking\ParseBundle\Tests\Models\Blog\Role;

class ACLTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\User',
        'Redking\ParseBundle\Tests\Models\Blog\Picture',
        'Redking\ParseBundle\Tests\Models\Blog\Role',
    ];

    public function testWithoutAcl()
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName('foo');

        $this->om->persist($user);
        $this->om->flush();

        $picture = new Picture();
        $picture->setFile('screen1.jpg');
        
        $this->om->persist($picture);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('foo');
        $picture = $this->om->getRepository(Picture::class)->findOneByFile('screen1.jpg');
        $this->assertNotNull($user);
        $this->assertNotNull($picture);

        $this->assertTrue($picture->getPublicAclReadAccess());
        $this->assertTrue($picture->getPublicAclWriteAccess());
        $this->assertTrue($user->getPublicAclReadAccess());
        $this->assertFalse($user->getPublicAclWriteAccess());

        $parseObject = $this->om->getUnitOfWork()->getOriginalObjectData($picture);
        $this->assertEquals(['*' => ['read' => true, 'write' => true]], $parseObject->getAcl()->_encode());
        
        $userParseObject = $this->om->getUnitOfWork()->getOriginalObjectData($user);
        $this->assertEquals(['*' => ['read' => true], $user->getId() => ['read' => true, 'write' => true]], $userParseObject->getAcl()->_encode());
    }

    public function testUserAcl()
    {
        $user = new User();
        $user->setName('foo');
        $user->setPassword('p4ss');

        $this->om->persist($user);
        $this->om->flush();

        $picture = new Picture();
        $picture->setFile('screen1.jpg');
        $picture->setPublicAcl(false, false);
        $picture->addUserAcl($user, true, true);

        $this->om->persist($picture);
        $this->om->flush();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->findOneByName('foo');
        $picture = $this->om->getRepository(Picture::class)->findOneByFile('screen1.jpg');
        $this->assertNotNull($user);
        $this->assertNotNull($picture);

        $this->assertFalse($picture->getPublicAclReadAccess());
        $this->assertFalse($picture->getPublicAclWriteAccess());

        $this->assertTrue($picture->getUserAclReadAccess($user));
        $this->assertTrue($picture->getUserAclWriteAccess($user));

        $parseObject = $this->om->getUnitOfWork()->getOriginalObjectData($picture);
        $this->assertEquals([$user->getId() => ['read' => true, 'write' => true]], $parseObject->getAcl()->_encode());
    }

    public function testRoleAcl()
    {
        $user = new User();
        $user->setName('foo');
        $user->setPassword('p4ss');

        $role = new Role();
        $role->setName('Foo');
        $role->addUser($user);

        $this->om->persist($user);
        $this->om->persist($role);
        $this->om->flush();
        $this->om->clear();

        $role = $this->om->getRepository(Role::class)->findOneByName('Foo');
        $this->assertNotNull($role);
        $this->assertCount(1, $role->getUsers());
        $this->assertTrue($role->getPublicAclReadAccess());
        $this->assertFalse($role->getPublicAclWriteAccess());

        $picture = new Picture();
        $picture->setFile('screen1.jpg');
        $picture->setPublicAcl(false, false);
        $picture->addRoleAcl($role, true, true);

        $this->om->persist($picture);
        $this->om->flush();
        $this->om->clear();

        $picture = $this->om->getRepository(Picture::class)->findOneByFile('screen1.jpg');
        $role = $this->om->getRepository(Role::class)->findOneByName('Foo');

        $this->assertNotNull($picture);

        $this->assertFalse($picture->getPublicAclReadAccess());
        $this->assertFalse($picture->getPublicAclWriteAccess());

        $this->assertTrue($picture->getRoleAclReadAccess($role));
        $this->assertTrue($picture->getRoleAclWriteAccess($role));

        $pictureParseObject = $this->om->getUnitOfWork()->getOriginalObjectData($picture);
        $this->assertEquals(['role:'.$role->getName() => ['read' => true, 'write' => true]], $pictureParseObject->getAcl()->_encode());

        // Update to see if the ACL are still there when no changes are made
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

        $picture = $this->om->getRepository(Picture::class)->findOneByFile('screen1.jpg');
        $role = $this->om->getRepository(Role::class)->findOneByName('Foo');
        $user = $this->om->getRepository(User::class)->findOneByName('foo');

        $this->assertNotNull($picture);
        $this->assertEquals($exif, $picture->getExif());

        $this->assertFalse($picture->getPublicAclReadAccess());
        $this->assertFalse($picture->getPublicAclWriteAccess());

        $this->assertTrue($picture->getRoleAclReadAccess($role));
        $this->assertTrue($picture->getRoleAclWriteAccess($role));

        // Update ACLs
        $picture->setPublicAcl(true, false);
        $picture->removeRoleAcl($role);
        $picture->addUserAcl($user, true, true);

        $this->om->persist($picture);
        $this->om->flush();
        $this->om->clear();

        $picture = $this->om->getRepository(Picture::class)->findOneByFile('screen1.jpg');
        $role = $this->om->getRepository(Role::class)->findOneByName('Foo');
        $user = $this->om->getRepository(User::class)->findOneByName('foo');

        $this->assertTrue($picture->getPublicAclReadAccess());
        $this->assertFalse($picture->getPublicAclWriteAccess());

        $this->assertTrue($picture->getRoleAclReadAccess($role)); // As public read is now true
        $this->assertFalse($picture->getRoleAclWriteAccess($role));

        $this->assertTrue($picture->getUserAclReadAccess($user));
        $this->assertTrue($picture->getUserAclWriteAccess($user));

        $pictureParseObject = $this->om->getUnitOfWork()->getOriginalObjectData($picture);
        $this->assertEquals(['*' => ['read' => true], $user->getId() => ['read' => true, 'write' => true]], $pictureParseObject->getAcl()->_encode());
    }
}
