<?php

namespace Redking\ParseBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\ACLTrait;

/**
 * Unit coverage for ACLTrait: public/role/user ACL bookkeeping, the permissive
 * defaults (no ACL set => read/write allowed) and key derivation for objects.
 */
class ACLTraitTest extends TestCase
{
    private function subject(): object
    {
        return new class () {
            use ACLTrait;
        };
    }

    public function testPublicAccessDefaultsToPermissiveWhenUnset(): void
    {
        $o = $this->subject();

        $this->assertNull($o->getPublicAcl());
        $this->assertTrue($o->getPublicAclReadAccess());
        $this->assertTrue($o->getPublicAclWriteAccess());
    }

    public function testSetPublicAcl(): void
    {
        $o = $this->subject();
        $o->setPublicAcl(true, false);

        $this->assertTrue($o->getPublicAclReadAccess());
        $this->assertFalse($o->getPublicAclWriteAccess());
    }

    public function testRoleAclLifecycle(): void
    {
        $o = $this->subject();
        $o->setPublicAcl(false, false);

        $o->addRoleAcl('admin', true, false);

        $this->assertTrue($o->hasRoleAcl('admin'));
        $this->assertTrue($o->getRoleAclReadAccess('admin'));
        $this->assertFalse($o->getRoleAclWriteAccess('admin'));

        // Unknown role falls back to public access (here: denied).
        $this->assertFalse($o->getRoleAclReadAccess('unknown'));
        $this->assertFalse($o->getRoleAclWriteAccess('unknown'));

        $o->removeRoleAcl('admin');
        $this->assertFalse($o->hasRoleAcl('admin'));
    }

    public function testUserAclLifecycle(): void
    {
        $o = $this->subject();
        $o->setPublicAcl(false, false);

        $o->addUserAcl('user-1', true, true);

        $this->assertTrue($o->getUserAclReadAccess('user-1'));
        $this->assertTrue($o->getUserAclWriteAccess('user-1'));
        $this->assertArrayHasKey('user-1', $o->getUsersAcl());

        // Unknown user falls back to public access.
        $this->assertFalse($o->getUserAclReadAccess('user-2'));

        $o->removeUserAcl('user-1');
        $this->assertArrayNotHasKey('user-1', $o->getUsersAcl());
    }

    public function testRolesAndUsersAclMassSetters(): void
    {
        $o = $this->subject();

        $o->setRolesAcl(['r' => ['role' => 'r', 'read' => true, 'write' => false]]);
        $o->setUsersAcl(['u' => ['user' => 'u', 'read' => false, 'write' => true]]);

        $this->assertTrue($o->getRoleAclReadAccess('r'));
        $this->assertFalse($o->getRoleAclWriteAccess('r'));
        $this->assertTrue($o->getUserAclWriteAccess('u'));
        $this->assertFalse($o->getUserAclReadAccess('u'));
    }

    public function testRoleKeyPrefersGetNameWhileUserKeyUsesGetId(): void
    {
        $principal = new class () {
            public function getName(): string
            {
                return 'the-name';
            }

            public function getId(): string
            {
                return 'the-id';
            }
        };

        $o = $this->subject();
        $o->addRoleAcl($principal, true, true);   // useName = true
        $o->addUserAcl($principal, true, true);   // useName = false -> getId

        $this->assertArrayHasKey('the-name', $o->getRolesAcl());
        $this->assertArrayHasKey('the-id', $o->getUsersAcl());
    }

    public function testRoleKeyFallsBackToIdWhenNameIsNull(): void
    {
        $principal = new class () {
            public function getName(): ?string
            {
                return null;
            }

            public function getId(): string
            {
                return 'fallback-id';
            }
        };

        $o = $this->subject();
        $o->addRoleAcl($principal, true, true);

        $this->assertArrayHasKey('fallback-id', $o->getRolesAcl());
    }
}
