<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseQuery;
use Redking\ParseBundle\Tests\Models\Blog\SecureDocument;

class EncryptedFieldTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\SecureDocument',
    ];

    public function testPersistAndRetrieveEncryptedField()
    {
        $doc = new SecureDocument();
        $doc->setTitle('Public Document');
        $doc->setSecretNote('This is a secret message');
        $doc->setSsn('123-45-6789');
        $doc->setPublicInfo('Public information');

        $this->om->persist($doc);
        $this->om->flush();

        $this->assertNotEmpty($doc->getId());

        // Retrieve from database
        $retrieved = $this->om->getRepository(SecureDocument::class)->find($doc->getId());

        $this->assertEquals('Public Document', $retrieved->getTitle());
        $this->assertEquals('This is a secret message', $retrieved->getSecretNote());
        $this->assertEquals('123-45-6789', $retrieved->getSsn());
        $this->assertEquals('Public information', $retrieved->getPublicInfo());
    }

    public function testEncryptedFieldIsActuallyEncrypted()
    {
        $doc = new SecureDocument();
        $doc->setTitle('Test Doc');
        $doc->setSecretNote('Secret data');
        $doc->setSsn('987-65-4321');

        $this->om->persist($doc);
        $this->om->flush();

        // Query Parse directly to verify data is encrypted
        $collection = $this->om->getClassMetadata(SecureDocument::class)->getCollection();
        $query = new ParseQuery($collection);
        $rawDoc = $query->get($doc->getId(), true);

        // Public field should be readable
        $this->assertEquals('Test Doc', $rawDoc->get('title'));

        // Encrypted fields should NOT contain the plaintext
        $encryptedNote = $rawDoc->get('secret_note');
        $encryptedSsn = $rawDoc->get('ssn');

        $this->assertNotEquals('Secret data', $encryptedNote);
        $this->assertNotEquals('987-65-4321', $encryptedSsn);

        // Encrypted values should be base64-like strings
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $encryptedNote);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $encryptedSsn);
    }

    public function testUpdateEncryptedField()
    {
        $doc = new SecureDocument();
        $doc->setTitle('Original Title');
        $doc->setSecretNote('Original Secret');

        $this->om->persist($doc);
        $this->om->flush();

        $id = $doc->getId();

        // Update the encrypted field
        $doc->setSecretNote('Updated Secret');
        $this->om->flush();

        // Clear and retrieve fresh
        $this->om->clear();
        $retrieved = $this->om->getRepository(SecureDocument::class)->find($id);

        $this->assertEquals('Updated Secret', $retrieved->getSecretNote());
    }

    public function testNullEncryptedField()
    {
        $doc = new SecureDocument();
        $doc->setTitle('Doc with null');
        $doc->setSecretNote(null);
        $doc->setSsn(null);

        $this->om->persist($doc);
        $this->om->flush();

        $retrieved = $this->om->getRepository(SecureDocument::class)->find($doc->getId());

        $this->assertNull($retrieved->getSecretNote());
        $this->assertNull($retrieved->getSsn());
    }

    public function testMultipleDocumentsWithEncryptedFields()
    {
        $doc1 = new SecureDocument();
        $doc1->setTitle('Doc 1');
        $doc1->setSecretNote('Secret 1');

        $doc2 = new SecureDocument();
        $doc2->setTitle('Doc 2');
        $doc2->setSecretNote('Secret 2');

        $this->om->persist($doc1);
        $this->om->persist($doc2);
        $this->om->flush();

        // Retrieve both
        $retrieved1 = $this->om->getRepository(SecureDocument::class)->find($doc1->getId());
        $retrieved2 = $this->om->getRepository(SecureDocument::class)->find($doc2->getId());

        $this->assertEquals('Secret 1', $retrieved1->getSecretNote());
        $this->assertEquals('Secret 2', $retrieved2->getSecretNote());
    }

    public function testRefreshEncryptedField()
    {
        $doc = new SecureDocument();
        $doc->setTitle('Refresh Test');
        $doc->setSecretNote('Original');

        $this->om->persist($doc);
        $this->om->flush();

        // Modify locally
        $doc->setSecretNote('Modified locally');

        // Refresh from database
        $this->om->refresh($doc);

        // Should revert to database value
        $this->assertEquals('Original', $doc->getSecretNote());
    }

    public function testEmptyStringEncryption()
    {
        $doc = new SecureDocument();
        $doc->setTitle('Empty test');
        $doc->setSecretNote('');
        $doc->setSsn('');

        $this->om->persist($doc);
        $this->om->flush();

        $retrieved = $this->om->getRepository(SecureDocument::class)->find($doc->getId());

        $this->assertEquals('', $retrieved->getSecretNote());
        $this->assertEquals('', $retrieved->getSsn());
    }

    public function testUnicodeEncryption()
    {
        $doc = new SecureDocument();
        $doc->setTitle('Unicode test');
        $doc->setSecretNote('Données sensibles avec accents: éèêë àâä 中文 العربية');

        $this->om->persist($doc);
        $this->om->flush();

        $retrieved = $this->om->getRepository(SecureDocument::class)->find($doc->getId());

        $this->assertEquals('Données sensibles avec accents: éèêë àâä 中文 العربية', $retrieved->getSecretNote());
    }

    public function testCheckNoChange()
    {
        $doc = new SecureDocument();
        $doc->setTitle('My Test');
        $doc->setSsn('Super secret');

        $this->om->persist($doc);
        $this->om->flush();
        $this->om->detach($doc);

        $retrieved = $this->om->getRepository(SecureDocument::class)->find($doc->getId());

        $this->om->getUnitOfWork()->computeChangeSets();
        $changeSets = $this->om->getUnitOfWork()->getObjectChangeSet($retrieved);
        $this->assertEmpty($changeSets, 'Check no UnitOfWork changes for freshly retrieved object with encryted field');
    }
}
