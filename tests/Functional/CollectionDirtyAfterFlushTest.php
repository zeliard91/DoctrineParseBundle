<?php

namespace Redking\ParseBundle\Tests\Functional;

use Redking\ParseBundle\PersistentCollection;
use Redking\ParseBundle\Tests\Models\Blog\Address;
use Redking\ParseBundle\Tests\Models\Blog\User;

/**
 * After a flush has persisted a collection update, the PersistentCollection
 * must take a snapshot of its current state and clear its dirty flag.
 * Otherwise subsequent calls to computeChangeSet (e.g. from a preUpdate
 * listener that calls recomputeSingleObjectChangeSet) will detect the
 * collection as dirty again and re-trigger its persister, producing
 * phantom Parse writes and duplicated audit log entries.
 */
class CollectionDirtyAfterFlushTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        User::class,
        Address::class,
    ];

    public function testRelationCollectionIsNotDirtyAfterFlush(): void
    {
        $user = new User();
        $user->setName('John');
        $user->setPassword('secret');

        $address1 = new Address();
        $address1->setCity('Paris');
        $user->addAddress($address1);

        $this->om->persist($user);
        $this->om->flush();
        $this->om->clear();

        /** @var User $user */
        $user = $this->om->getRepository(User::class)->findOneBy(['name' => 'John']);
        self::assertNotNull($user);
        self::assertCount(1, $user->getAddresses());

        $address2 = new Address();
        $address2->setCity('Lyon');
        $user->addAddress($address2);

        $addresses = $user->getAddresses();
        self::assertInstanceOf(PersistentCollection::class, $addresses);
        self::assertTrue($addresses->isDirty(), 'Collection must be dirty after adding an element');

        $this->om->flush();

        self::assertFalse(
            $addresses->isDirty(),
            'PersistentCollection must take a snapshot and clear its dirty flag after flush'
        );

        $snapshot = $addresses->getSnapshot();
        self::assertCount(2, $snapshot, 'Snapshot must reflect the persisted state (2 addresses)');
    }
}
