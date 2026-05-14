<?php

namespace Redking\ParseBundle\Tests\Functional;

use Doctrine\Persistence\Proxy as DoctrinePersistenceProxy;
use Redking\ParseBundle\Configuration;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\Registry;
use Redking\ParseBundle\Tests\Models\Blog\Invoice;
use Symfony\Component\DependencyInjection\Container;

/**
 * Reproduces the downstream bundle bug where Registry::getManagerForClass()
 * was called with a generated proxy class name and returned null because the
 * proxy did not implement Doctrine\Persistence\Proxy (the marker the
 * AbstractManagerRegistry uses to strip the proxy prefix).
 */
class RegistryProxyTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        Invoice::class,
    ];

    public function testGetManagerForClassResolvesProxyClass(): void
    {
        $container = new Container();
        $container->set('redking_parse.manager', $this->om);

        $registry = new Registry($container, 'redking_parse.manager');

        $invoice = new Invoice();
        $invoice->setReference('REGISTRY-PROXY-001');
        $this->om->persist($invoice);
        $this->om->flush();

        $id = $invoice->getId();
        $this->om->clear();

        $reference  = $this->om->getReference(Invoice::class, $id);
        $proxyClass = get_class($reference);

        $manager = $registry->getManagerForClass($proxyClass);

        $this->assertInstanceOf(
            ObjectManager::class,
            $manager,
            sprintf('Registry must resolve the manager for proxy class %s', $proxyClass)
        );
    }

    public function testVarExporterProxyImplementsDoctrinePersistenceProxy(): void
    {
        if ($this->om->getConfiguration()->isLazyGhostObjectEnabled()) {
            $this->markTestSkipped('Native lazy ghosts re-use the entity class itself — no marker interface needed.');
        }

        $invoice = new Invoice();
        $invoice->setReference('REGISTRY-PROXY-002');
        $this->om->persist($invoice);
        $this->om->flush();

        $id = $invoice->getId();
        $this->om->clear();

        $reference = $this->om->getReference(Invoice::class, $id);

        $this->assertNotSame(
            Invoice::class,
            get_class($reference),
            'Var-exporter mode must generate a distinct proxy class.'
        );

        $this->assertInstanceOf(
            DoctrinePersistenceProxy::class,
            $reference,
            'Generated proxies must implement Doctrine\\Persistence\\Proxy so the Symfony Bridge ManagerRegistry can strip the proxy prefix.'
        );
    }
}
