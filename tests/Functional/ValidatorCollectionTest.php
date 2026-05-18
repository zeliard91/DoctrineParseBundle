<?php

namespace Redking\ParseBundle\Tests\Functional;

use Redking\ParseBundle\PersistentCollection;
use Redking\ParseBundle\Tests\Models\Blog\ValidatedAuthor;
use Redking\ParseBundle\Tests\Models\Blog\ValidatedBook;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

/**
 * Verifies that Symfony's Assert\Valid constraint on a lazy ReferenceMany
 * collection triggers its initialization and reaches the children's own
 * constraints (e.g. NotBlank on a child field).
 */
class ValidatorCollectionTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        ValidatedAuthor::class,
        ValidatedBook::class,
    ];

    private ValidatorInterface $validator;

    public function setUp(): void
    {
        parent::setUp();

        $builder = Validation::createValidatorBuilder();
        // Symfony >= 6.4 exposes enableAttributeMapping(); on Symfony 5.4 the
        // attribute reader is enabled through enableAnnotationMapping(true),
        // where the boolean skips the Doctrine annotation reader so the
        // doctrine/annotations package is not required.
        if (method_exists($builder, 'enableAttributeMapping')) {
            $builder->enableAttributeMapping();
        } else {
            $builder->enableAnnotationMapping(true);
        }

        $this->validator = $builder->getValidator();
    }

    public function testValidCascadesIntoLazyCollectionAndDetectsViolation(): void
    {
        $author = new ValidatedAuthor();
        $author->setName('Author With Invalid Book');

        $validBook = new ValidatedBook();
        $validBook->setTitle('A Valid Title');
        $author->addBook($validBook);

        // The second book is persisted with a non-empty title to satisfy the
        // database layer, then will be reset to a blank value once reloaded
        // (Parse does not allow persisting a fully empty required field on
        // some setups but the in-memory mutation is enough to trigger the
        // NotBlank violation through Assert\Valid).
        $invalidBook = new ValidatedBook();
        $invalidBook->setTitle('Temporary');
        $author->addBook($invalidBook);

        $this->om->persist($author);
        $this->om->flush();
        $this->om->clear();

        $loadedAuthor = $this->om->getRepository(ValidatedAuthor::class)
            ->findOneByName('Author With Invalid Book');

        $this->assertNotNull($loadedAuthor);

        $books = $loadedAuthor->getBooks();
        $this->assertInstanceOf(PersistentCollection::class, $books);
        $this->assertFalse(
            $books->isInitialized(),
            'The ReferenceMany collection must remain lazy before validation runs'
        );

        $violations = $this->validator->validate($loadedAuthor);

        $this->assertTrue(
            $books->isInitialized(),
            'Assert\\Valid must trigger the lazy initialization of the collection'
        );
        $this->assertCount(2, $books, 'Both books must be loaded during validation');

        $this->assertCount(
            0,
            $violations,
            'A collection of valid children produces no violation'
        );

        // Now break one child and confirm the cascaded validation catches it.
        foreach ($books as $book) {
            if ($book->getTitle() === 'Temporary') {
                $book->setTitle('');
                break;
            }
        }

        $violations = $this->validator->validate($loadedAuthor);

        $this->assertCount(
            1,
            $violations,
            'Assert\\Valid must surface the NotBlank violation on the lazily-loaded child'
        );

        $violation = $violations->get(0);
        $this->assertStringStartsWith(
            'books[',
            $violation->getPropertyPath(),
            'The violation must point at the children collection property path'
        );
        $this->assertStringEndsWith(
            '].title',
            $violation->getPropertyPath(),
            'The violation must target the child\'s NotBlank-protected field'
        );
    }

    public function testValidatorReportsNoViolationWhenChildrenAreValid(): void
    {
        $author = new ValidatedAuthor();
        $author->setName('Author With Valid Books');

        $book1 = new ValidatedBook();
        $book1->setTitle('First');
        $author->addBook($book1);

        $book2 = new ValidatedBook();
        $book2->setTitle('Second');
        $author->addBook($book2);

        $this->om->persist($author);
        $this->om->flush();
        $this->om->clear();

        $loadedAuthor = $this->om->getRepository(ValidatedAuthor::class)
            ->findOneByName('Author With Valid Books');

        $this->assertNotNull($loadedAuthor);
        $this->assertFalse(
            $loadedAuthor->getBooks()->isInitialized(),
            'The ReferenceMany collection must remain lazy before validation runs'
        );

        $violations = $this->validator->validate($loadedAuthor);

        $this->assertCount(0, $violations);
        $this->assertTrue(
            $loadedAuthor->getBooks()->isInitialized(),
            'Assert\\Valid must trigger the lazy initialization of the collection'
        );
    }
}
