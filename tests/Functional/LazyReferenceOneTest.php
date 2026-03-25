<?php

namespace Redking\ParseBundle\Tests\Functional;

use Doctrine\Common\Proxy\Proxy;
use Redking\ParseBundle\Tests\Models\Blog\Article;
use Redking\ParseBundle\Tests\Models\Blog\BankOperation;
use Redking\ParseBundle\Tests\Models\Blog\Invoice;
use Redking\ParseBundle\Tests\Models\Blog\Tag;

class LazyReferenceOneTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        BankOperation::class,
        Invoice::class,
        Article::class,
        Tag::class,
    ];

    /**
     * A lazy proxy is injected at hydration time (not yet initialized).
     * It loads on the first property access.
     */
    public function testLazyProxyIsNotInitializedAtHydration(): void
    {
        $invoice = new Invoice();
        $invoice->setReference('INV-001');
        $this->om->persist($invoice);

        $bankOp = new BankOperation();
        $bankOp->setAmount(99.50);
        $bankOp->setInvoice($invoice);
        $this->om->persist($bankOp);

        $this->om->flush();
        $this->om->clear();

        $loaded = $this->om->getRepository(Invoice::class)->findOneByReference('INV-001');
        $this->assertNotNull($loaded);

        $proxy = $loaded->getBankOperation();

        // The field must hold a proxy, not null and not a fully loaded object
        $this->assertInstanceOf(Proxy::class, $proxy, 'bankOperation must be a lazy proxy');
        $this->assertFalse($proxy->__isInitialized(), 'The proxy must not be initialized at hydration time');
    }

    /**
     * Accessing a property on the proxy triggers initialization
     * and returns the correct data.
     */
    public function testLazyProxyInitializesOnFirstAccess(): void
    {
        $invoice = new Invoice();
        $invoice->setReference('INV-002');
        $this->om->persist($invoice);

        $bankOp = new BankOperation();
        $bankOp->setAmount(150.00);
        $bankOp->setInvoice($invoice);
        $this->om->persist($bankOp);

        $this->om->flush();
        $this->om->clear();

        $loaded = $this->om->getRepository(Invoice::class)->findOneByReference('INV-002');
        $proxy = $loaded->getBankOperation();

        $this->assertInstanceOf(Proxy::class, $proxy);
        $this->assertFalse($proxy->__isInitialized(), 'Not yet initialized before the first access');

        // First access: triggers the query
        $amount = $proxy->getAmount();

        $this->assertTrue($proxy->__isInitialized(), 'Proxy initialized after the first access');
        $this->assertEquals(150.00, $amount, 'Loaded data is correct');
    }

    /**
     * When no BankOperation references the invoice,
     * the field becomes null after the proxy is initialized.
     */
    public function testLazyProxyBecomesNullWhenNoRelatedObject(): void
    {
        $invoice = new Invoice();
        $invoice->setReference('INV-003');
        $this->om->persist($invoice);
        $this->om->flush();
        $this->om->clear();

        $loaded = $this->om->getRepository(Invoice::class)->findOneByReference('INV-003');
        $this->assertNotNull($loaded);

        // At hydration, a proxy is still injected
        $proxy = $loaded->getBankOperation();
        $this->assertInstanceOf(Proxy::class, $proxy, 'A proxy is injected even when no relation exists');
        $this->assertFalse($proxy->__isInitialized());

        // Accessing the proxy: no relation found -> the field is set to null
        $proxy->getAmount();

        $this->assertNull(
            $loaded->getBankOperation(),
            'The field must be null after initialization if no related object exists'
        );
    }

    /**
     * Without fetch: 'LAZY', the default eager behaviour is preserved (no proxy).
     */
    public function testEagerLoadingIsPreservedByDefault(): void
    {
        $invoice = new Invoice();
        $invoice->setReference('INV-004');
        $this->om->persist($invoice);

        $bankOp = new BankOperation();
        $bankOp->setAmount(200.00);
        $bankOp->setInvoice($invoice);
        $this->om->persist($bankOp);

        $this->om->flush();
        $this->om->clear();

        // BankOperation.invoice is the owning side without fetch: 'LAZY'
        // -> at hydration of BankOperation, $invoice is resolved directly (by-ID proxy, not inverse lookup)
        $loadedOp = $this->om->getRepository(BankOperation::class)
            ->findOneByAmount(200.00);

        $this->assertNotNull($loadedOp);
        // The related object (owning side) must be resolved, whether via ID proxy or direct object
        $this->assertNotNull($loadedOp->getInvoice());
    }

    // -----------------------------------------------------------------------
    // Case: lazy inverse ReferenceOne whose opposite side is a ReferenceMany
    // Article stores an array of Tag pointers (ReferenceMany owning side),
    // Tag exposes a lazy ReferenceOne back to Article (inverse via mappedBy).
    // -----------------------------------------------------------------------

    /**
     * A lazy proxy is injected at hydration of a Tag
     * when the opposite side is a ReferenceMany (Article.tags).
     */
    public function testLazyProxyViaReferenceManyOwningSide(): void
    {
        $tag = new Tag();
        $tag->setName('php');
        $this->om->persist($tag);

        $article = new Article();
        $article->setTitle('Introduction to PHP');
        $article->addTag($tag);
        $this->om->persist($article);

        $this->om->flush();
        $this->om->clear();

        $loadedTag = $this->om->getRepository(Tag::class)->findOneByName('php');
        $this->assertNotNull($loadedTag);

        $proxy = $loadedTag->getArticle();

        $this->assertInstanceOf(Proxy::class, $proxy, 'article must be a lazy proxy');
        $this->assertFalse($proxy->__isInitialized(), 'The proxy must not be initialized at hydration time');
    }

    /**
     * The lazy proxy (opposite is ReferenceMany) loads correctly
     * on the first access and returns the correct data.
     */
    public function testLazyProxyViaReferenceManyInitializesOnFirstAccess(): void
    {
        $tag = new Tag();
        $tag->setName('symfony');
        $this->om->persist($tag);

        $article = new Article();
        $article->setTitle('Symfony in practice');
        $article->addTag($tag);
        $this->om->persist($article);

        $this->om->flush();
        $this->om->clear();

        $loadedTag = $this->om->getRepository(Tag::class)->findOneByName('symfony');
        $proxy = $loadedTag->getArticle();

        $this->assertInstanceOf(Proxy::class, $proxy);
        $this->assertFalse($proxy->__isInitialized());

        // First access: triggers the query via field('tags')->references(tag)
        $title = $proxy->getTitle();

        $this->assertTrue($proxy->__isInitialized());
        $this->assertEquals('Symfony in practice', $title);
    }

    /**
     * A Tag with no related Article: the field becomes null after proxy initialization.
     */
    public function testLazyProxyViaReferenceManyBecomesNullWhenNoArticle(): void
    {
        $tag = new Tag();
        $tag->setName('orphan-tag');
        $this->om->persist($tag);
        $this->om->flush();
        $this->om->clear();

        $loadedTag = $this->om->getRepository(Tag::class)->findOneByName('orphan-tag');
        $this->assertNotNull($loadedTag);

        $proxy = $loadedTag->getArticle();
        $this->assertInstanceOf(Proxy::class, $proxy);
        $this->assertFalse($proxy->__isInitialized());

        // Access: no Article references this tag -> null
        $proxy->getTitle();

        $this->assertNull(
            $loadedTag->getArticle(),
            'The field must be null if no Article references this Tag'
        );
    }
}
