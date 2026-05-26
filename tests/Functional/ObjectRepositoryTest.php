<?php

namespace Redking\ParseBundle\Tests\Functional;

use Doctrine\Common\Collections\Criteria;
use Redking\ParseBundle\Exception\RedkingParseException;
use Redking\ParseBundle\ObjectRepository;
use Redking\ParseBundle\QueryBuilder;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;

/**
 * Coverage for ObjectRepository: accessors, the find/findBy family, the magic
 * findByXxx/findOneByXxx finders and their error paths, plus the do_not_manage
 * variant findByWithoutManaging().
 */
class ObjectRepositoryTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        ChainNode::class,
    ];

    private function repository(): ObjectRepository
    {
        return $this->om->getRepository(ChainNode::class);
    }

    private function persistNode(string $label): ChainNode
    {
        $node = new ChainNode();
        $node->setLabel($label);
        $this->om->persist($node);
        $this->om->flush();

        return $node;
    }

    // --- accessors / no database round-trip ----------------------------------

    public function testAccessors(): void
    {
        $repo = $this->repository();

        $this->assertSame(ChainNode::class, $repo->getClassName());
        $this->assertSame($this->om, $repo->getObjectManager());
        $this->assertSame(ChainNode::class, $repo->getClassMetadata()->name);
        $this->assertInstanceOf(QueryBuilder::class, $repo->createQueryBuilder());
    }

    public function testFindWithNullReturnsNull(): void
    {
        $this->assertNull($this->repository()->find(null));
    }

    public function testMatchingIsAnUnimplementedStub(): void
    {
        $this->assertNull($this->repository()->matching(Criteria::create()));
    }

    public function testMagicCallOnNonFinderThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->repository()->getSomething('x');
    }

    public function testMagicFinderWithoutArgumentThrows(): void
    {
        $this->expectException(RedkingParseException::class);
        $this->repository()->findByLabel();
    }

    public function testMagicFinderOnUnknownFieldThrows(): void
    {
        $this->expectException(RedkingParseException::class);
        $this->repository()->findByNonExistentField('x');
    }

    // --- finders that hit Parse Server ---------------------------------------

    public function testFindByIdentityMapHitReturnsSameInstance(): void
    {
        $node = $this->persistNode('solo');
        $id = $node->getId();
        $this->om->clear();

        $first = $this->repository()->find($id);
        $this->assertInstanceOf(ChainNode::class, $first);
        $this->assertSame('solo', $first->getLabel());

        // Second lookup must come straight from the identity map.
        $second = $this->repository()->find($id);
        $this->assertSame($first, $second);
    }

    public function testFindAcceptsIdentifierArray(): void
    {
        $node = $this->persistNode('arr');
        $id = $node->getId();
        $this->om->clear();

        $found = $this->repository()->find(['id' => $id]);
        $this->assertNotNull($found);
        $this->assertSame('arr', $found->getLabel());
    }

    public function testFindOneByAndFindByAndFindAll(): void
    {
        $this->persistNode('alpha');
        $this->om->clear();

        $one = $this->repository()->findOneBy(['label' => 'alpha']);
        $this->assertInstanceOf(ChainNode::class, $one);
        $this->assertSame('alpha', $one->getLabel());

        $many = $this->repository()->findBy(['label' => 'alpha']);
        $this->assertCount(1, $many);

        $this->assertCount(1, $this->repository()->findAll());
    }

    public function testMagicFinders(): void
    {
        $this->persistNode('magic');
        $this->om->clear();

        $one = $this->repository()->findOneByLabel('magic');
        $this->assertInstanceOf(ChainNode::class, $one);
        $this->assertSame('magic', $one->getLabel());

        $many = $this->repository()->findByLabel('magic');
        $this->assertCount(1, $many);
    }

    public function testFindByWithoutManagingReturnsDetachedResults(): void
    {
        $node = $this->persistNode('unmanaged');
        $id = $node->getId();
        $this->om->clear();

        $results = $this->repository()->findByWithoutManaging(['label' => 'unmanaged']);

        $collected = [];
        foreach ($results as $r) {
            $collected[] = $r;
        }
        $this->assertCount(1, $collected);
        $this->assertSame('unmanaged', $collected[0]->getLabel());
        $this->assertFalse($this->om->contains($collected[0]), 'results must not be managed');

        // A subsequent flush must not wipe the detached object.
        $this->om->flush();
        $this->om->clear();

        $reloaded = $this->repository()->find($id);
        $this->assertSame('unmanaged', $reloaded->getLabel());
    }
}
