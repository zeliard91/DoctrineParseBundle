<?php

namespace Redking\ParseBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;

/**
 * A lazy reference must behave the same with generated proxies and with PHP 8.4 native lazy
 * ghosts: the usual unit of work operations must not load it as long as the code does not
 * read it.
 *
 * Native ghosts are initialized by any access to their state from outside a method
 * (comparison, json_encode(), serialize(), clone...), which generated proxies are not, so an
 * operation of the bundle relying on one of those loads the references in native mode only.
 */
class LazyReferenceParityTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        ChainNode::class,
    ];

    /**
     * @return iterable<string, array{0: bool}>
     */
    public static function proxyModes(): iterable
    {
        yield 'generated proxies' => [false];
        yield 'native lazy ghosts' => [true];
    }

    #[DataProvider('proxyModes')]
    public function testUpdatingTheOwnerKeepsItsReferenceLazy(bool $useLazyGhostObject): void
    {
        $this->useProxyMode($useLazyGhostObject);
        [$target, $owner] = $this->createOwnerOf('target');

        $owner = $this->reload($owner);
        $reference = $owner->getFirst();
        $this->assertLazy($reference, 'The reference is loaded lazily');

        $owner->setLabel('updated');
        $this->om->flush();

        $this->assertLazy($reference);
        $this->assertSame('updated', $this->reload($owner)->getLabel());
    }

    #[DataProvider('proxyModes')]
    public function testReplacingAReferenceKeepsTheOldOneLazy(bool $useLazyGhostObject): void
    {
        $this->useProxyMode($useLazyGhostObject);
        [$target, $owner] = $this->createOwnerOf('target');
        $replacement = $this->createNode('replacement');

        $owner = $this->reload($owner);
        $oldReference = $owner->getFirst();
        $owner->setFirst($this->om->find(ChainNode::class, $replacement->getId()));
        $this->om->flush();

        $this->assertLazy($oldReference);
        $this->assertSame($replacement->getId(), $this->reload($owner)->getFirst()->getId());
    }

    #[DataProvider('proxyModes')]
    public function testSettingTheSameReferenceAgainKeepsItLazy(bool $useLazyGhostObject): void
    {
        $this->useProxyMode($useLazyGhostObject);
        [$target, $owner] = $this->createOwnerOf('target');

        $owner = $this->reload($owner);
        $reference = $owner->getFirst();
        $owner->setFirst($this->om->getReference(ChainNode::class, $target->getId()));
        $this->uow->computeChangeSets();

        $this->assertLazy($reference);
        $this->assertSame([], $this->uow->getObjectChangeSet($owner), 'The same reference is not a change');
    }

    #[DataProvider('proxyModes')]
    public function testRemovingTheOwnerKeepsItsReferenceLazy(bool $useLazyGhostObject): void
    {
        $this->useProxyMode($useLazyGhostObject);
        [$target, $owner] = $this->createOwnerOf('target');

        $owner = $this->reload($owner);
        $reference = $owner->getFirst();
        $this->om->remove($owner);
        $this->om->flush();

        $this->assertLazy($reference);
    }

    #[DataProvider('proxyModes')]
    public function testQueryingByAReferenceKeepsItLazy(bool $useLazyGhostObject): void
    {
        $this->useProxyMode($useLazyGhostObject);
        [$target, $owner] = $this->createOwnerOf('target');
        $this->om->clear();

        $reference = $this->om->getReference(ChainNode::class, $target->getId());
        $owners = $this->om->getRepository(ChainNode::class)
            ->createQueryBuilder()
            ->field('first')->references($reference)
            ->getQuery()
            ->execute();

        $this->assertSame([$owner->getId()], array_map(static fn (ChainNode $node): string => $node->getId(), iterator_to_array($owners, false)));
        $this->assertLazy($reference);
    }

    #[DataProvider('proxyModes')]
    public function testALazyReferenceKeptAcrossAClearCanBeAssignedAgain(bool $useLazyGhostObject): void
    {
        $this->useProxyMode($useLazyGhostObject);
        [$target, $owner] = $this->createOwnerOf('target');

        $reference = $this->reload($owner)->getFirst();
        $this->om->clear();

        $owner = $this->om->find(ChainNode::class, $owner->getId());
        $owner->setSecond($reference);
        $this->om->flush();

        $this->assertSame($target->getId(), $this->reload($owner)->getSecond()?->getId());
    }

    #[DataProvider('proxyModes')]
    public function testAReferenceObtainedWithoutLoadingCanBeAssigned(bool $useLazyGhostObject): void
    {
        $this->useProxyMode($useLazyGhostObject);
        [$target, $owner] = $this->createOwnerOf('target');
        // not referenced by the owner, so its loading does not put it in the identity map
        $other = $this->createNode('other');
        $this->om->clear();

        $owner = $this->om->find(ChainNode::class, $owner->getId());
        $reference = $this->om->getReference(ChainNode::class, $other->getId());
        $owner->setSecond($reference);
        $this->om->flush();

        $this->assertLazy($reference);
        $this->assertSame($other->getId(), $this->reload($owner)->getSecond()?->getId());
    }

    private function useProxyMode(bool $useLazyGhostObject): void
    {
        if ($useLazyGhostObject && PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('Native lazy ghosts require PHP 8.4+.');
        }

        $this->om = $this->createTestObjectManager($useLazyGhostObject);
        $this->uow = $this->om->getUnitOfWork();
    }

    private function createNode(string $label): ChainNode
    {
        $node = (new ChainNode())->setLabel($label);
        $this->om->persist($node);
        $this->om->flush();

        return $node;
    }

    /**
     * @return array{0: ChainNode, 1: ChainNode} the target and its owner, whose first node it is
     */
    private function createOwnerOf(string $label): array
    {
        $target = $this->createNode($label);
        $owner = (new ChainNode())->setLabel('owner')->setFirst($target);
        $this->om->persist($owner);
        $this->om->flush();

        return [$target, $owner];
    }

    private function reload(ChainNode $node): ChainNode
    {
        $id = $node->getId();
        $this->om->clear();

        return $this->om->find(ChainNode::class, $id);
    }

    private function assertLazy(?object $reference, string $message = 'The reference must not be loaded'): void
    {
        $this->assertNotNull($reference);
        $this->assertTrue($this->om->isUninitializedObject($reference), $message);
    }
}
