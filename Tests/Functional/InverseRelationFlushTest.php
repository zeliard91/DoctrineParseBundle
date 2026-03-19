<?php

namespace Redking\ParseBundle\Tests\Functional;

use Redking\ParseBundle\Tests\Models\Blog\BankOperation;
use Redking\ParseBundle\Tests\Models\Blog\Invoice;

/**
 * Reproduces the "new document found through relationship" error that occurs
 * when flush() is called after a lazy inverse ReferenceOne proxy has been
 * initialized (e.g. during an export loop).
 *
 * The sequence that triggers the bug:
 *   1. Load an Invoice from Parse.
 *   2. Access invoice->getBankOperation() → lazy proxy initializes, loads a
 *      BankOperation from Parse and registers it in the UoW.
 *      The proxy object itself is NOT registered in the UoW.
 *   3. Call flush() → computeChangeSet(invoice) iterates associationMappings,
 *      reaches the inverse "bankOperation" field, finds the unregistered proxy
 *      and throws RedkingParseException::newObjectFoundThroughRelationship.
 */
class InverseRelationFlushTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        BankOperation::class,
        Invoice::class,
    ];

    /**
     * Flush after accessing a lazy inverse ReferenceOne must not throw.
     */
    public function testFlushAfterAccessingLazyInverseReferenceOne(): void
    {
        // -- Setup --
        $invoice = new Invoice();
        $invoice->setReference('INV-FLUSH-001');
        $this->om->persist($invoice);

        $bankOp = new BankOperation();
        $bankOp->setAmount(42.00);
        $bankOp->setInvoice($invoice);
        $this->om->persist($bankOp);

        $this->om->flush();
        $this->om->clear();

        // -- Reproduce export loop pattern --
        // 1. Load the invoice (UoW now manages it)
        $loaded = $this->om->getRepository(Invoice::class)->findOneByReference('INV-FLUSH-001');
        $this->assertNotNull($loaded);

        // 2. Access the inverse relation → initializes the lazy proxy
        $proxy = $loaded->getBankOperation();
        $this->assertNotNull($proxy);
        $amount = $proxy->getAmount(); // triggers proxy initialization
        $this->assertEquals(42.00, $amount);

        // 3. Flush → must NOT throw
        //    Before the fix: throws RedkingParseException::newObjectFoundThroughRelationship
        //    After  the fix: inverse associations are skipped in computeChangeSet
        $this->om->flush();

        // If we reach here the bug is fixed
        $this->assertTrue(true, 'flush() after lazy inverse proxy access must not throw');
    }

    /**
     * Same scenario with multiple invoices, simulating an export page iteration.
     */
    public function testFlushAfterAccessingLazyInverseOnMultipleObjects(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $invoice = new Invoice();
            $invoice->setReference("INV-MULTI-00{$i}");
            $this->om->persist($invoice);

            $bankOp = new BankOperation();
            $bankOp->setAmount((float) ($i * 10));
            $bankOp->setInvoice($invoice);
            $this->om->persist($bankOp);
        }

        $this->om->flush();
        $this->om->clear();

        // Load all invoices and access the inverse relation on each one
        $invoices = $this->om->getRepository(Invoice::class)->findAll();
        $this->assertCount(3, $invoices);

        foreach ($invoices as $inv) {
            $proxy = $inv->getBankOperation();
            if ($proxy !== null) {
                $proxy->getAmount(); // initialize
            }
        }

        // Flush covering all managed invoices with initialized proxies
        $this->om->flush();

        $this->assertTrue(true, 'flush() after iterating lazy inverse proxies must not throw');
    }
}
