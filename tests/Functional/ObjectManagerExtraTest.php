<?php

namespace Redking\ParseBundle\Tests\Functional;

use Doctrine\Common\EventManager;
use Redking\ParseBundle\Configuration;
use Redking\ParseBundle\Mapping\ClassMetadataFactory;
use Redking\ParseBundle\Proxy\ProxyFactory;
use Redking\ParseBundle\SchemaManager;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;
use Redking\ParseBundle\UnitOfWork;

/**
 * Coverage for ObjectManager plumbing not exercised elsewhere: argument-type
 * guards, infrastructure accessors, getReference() proxy creation, lazy-object
 * initialization helpers and request-mode detection.
 */
class ObjectManagerExtraTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        ChainNode::class,
    ];

    private function persistNode(string $label): ChainNode
    {
        $node = new ChainNode();
        $node->setLabel($label);
        $this->om->persist($node);
        $this->om->flush();

        return $node;
    }

    public function testMutatorsRejectNonObjects(): void
    {
        $om = $this->om;
        $cases = [
            'persist' => static fn () => $om->persist('x'),
            'remove' => static fn () => $om->remove('x'),
            'merge' => static fn () => $om->merge('x'),
            'detach' => static fn () => $om->detach('x'),
            'refresh' => static fn () => $om->refresh('x'),
            'flush-scalar' => static fn () => $om->flush('x'),
        ];

        foreach ($cases as $name => $fn) {
            try {
                $fn();
                $this->fail(sprintf('%s() must reject a non-object argument', $name));
            } catch (\InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testInfrastructureAccessors(): void
    {
        $this->assertInstanceOf(ProxyFactory::class, $this->om->getProxyFactory());
        $this->assertInstanceOf(ClassMetadataFactory::class, $this->om->getMetadataFactory());
        $this->assertInstanceOf(SchemaManager::class, $this->om->getSchemaManager());
        $this->assertInstanceOf(Configuration::class, $this->om->getConfiguration());
        $this->assertInstanceOf(EventManager::class, $this->om->getEventManager());
        $this->assertInstanceOf(UnitOfWork::class, $this->om->getUnitOfWork());
    }

    public function testGetReferenceReturnsManagedUninitializedProxy(): void
    {
        $node = $this->persistNode('ref');
        $id = $node->getId();
        $this->om->clear();

        $ref = $this->om->getReference(ChainNode::class, $id);

        $this->assertInstanceOf(ChainNode::class, $ref);
        $this->assertTrue($this->om->isUninitializedObject($ref), 'a fresh reference must be an uninitialized proxy');
        $this->assertTrue($this->om->contains($ref), 'the reference must be managed');

        // A second call hits the identity map and returns the same instance.
        $this->assertSame($ref, $this->om->getReference(ChainNode::class, $id));
    }

    public function testInitializeObjectLoadsTheProxy(): void
    {
        $node = $this->persistNode('lazy');
        $id = $node->getId();
        $this->om->clear();

        $ref = $this->om->getReference(ChainNode::class, $id);
        $this->assertTrue($this->om->isUninitializedObject($ref));

        $this->om->initializeObject($ref);

        $this->assertFalse($this->om->isUninitializedObject($ref));
        $this->assertSame('lazy', $ref->getLabel());
    }

    public function testInitializeObjectIgnoresNonObjects(): void
    {
        $this->om->initializeObject('not-an-object');
        $this->om->initializeObject(42);
        $this->addToAssertionCount(1); // no exception means success
    }

    public function testIsUninitializedObjectIsFalseForNonObjects(): void
    {
        $this->assertFalse($this->om->isUninitializedObject('x'));
        $this->assertFalse($this->om->isUninitializedObject(42));
        $this->assertFalse($this->om->isUninitializedObject(null));
    }

    public function testContainsReflectsManagementState(): void
    {
        $node = $this->persistNode('contained');

        $this->assertTrue($this->om->contains($node));

        $this->om->detach($node);
        $this->assertFalse($this->om->contains($node));
    }

    public function testFindDelegatesToRepository(): void
    {
        $node = $this->persistNode('viaFind');
        $id = $node->getId();
        $this->om->clear();

        $this->assertNull($this->om->find(ChainNode::class, null));

        $found = $this->om->find(ChainNode::class, $id);
        $this->assertInstanceOf(ChainNode::class, $found);
        $this->assertSame('viaFind', $found->getLabel());
    }

    public function testIsMasterRequestWhenNoCurrentUser(): void
    {
        // No Parse user is authenticated during tests, so requests use the master key.
        $this->assertTrue($this->om->isMasterRequest());
    }
}
