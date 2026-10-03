<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseFile;
use Redking\ParseBundle\Form\Type\ParseFileType;
use Redking\ParseBundle\Form\UploadedParseFileRegistry;
use Redking\ParseBundle\Tests\Models\Blog\Picture;
use Redking\ParseBundle\Tests\TestCase;
use Redking\ParseBundle\Validator\Constraints\ParseFile as ParseFileConstraint;
use Redking\ParseBundle\Validator\Constraints\ParseFileImage;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

/**
 * Drives a ParseFileType through a real form submission (request handler, model transformer,
 * validator extension), then saves the resulting ParseFile on Parse Server.
 *
 * The ParseFile validators only see a ParseFile: they must get back the UploadedFile it was
 * built from to check its size, mime type or dimensions, before the file is ever uploaded.
 */
class ParseFileUploadTest extends TestCase
{
    protected static $modelSets = [
        Picture::class,
    ];

    // 2x2 transparent PNG
    private const PNG_2X2 = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEklEQVR4nGNgYGD4z8DAwMAAAAQAAQAhPgQjAAAAAElFTkSuQmCC';

    private FormFactoryInterface $factory;

    /** @var string[] */
    private array $tmpFiles = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([new ParseFileType($this->om)], []))
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
        ;
    }

    public function tearDown(): void
    {
        foreach ($this->tmpFiles as $tmpFile) {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
        }

        parent::tearDown();
    }

    public function testValidUploadIsLinkedToItsParseFileAndSaved(): void
    {
        $content = "Uploaded through a form\n";
        $uploadedFile = $this->createUploadedFile($content, 'Mon document.txt', 'text/plain');

        $picture = new Picture();
        $form = $this->submit($picture, $uploadedFile, [new ParseFileConstraint(maxSize: '1k')]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));

        $media = $picture->getMedia();
        $this->assertInstanceOf(ParseFile::class, $media);
        $this->assertNull($media->getURL(), 'The file must not be uploaded before the object is flushed');
        $this->assertSame('Mon-document.txt', $media->getName());
        $this->assertSame($uploadedFile, UploadedParseFileRegistry::get($media));

        $this->om->persist($picture);
        $this->om->flush();
        $this->om->clear();

        $saved = $this->om->getRepository(Picture::class)->findOneByFile('form-upload');
        $this->assertNotNull($saved);
        $this->assertInstanceOf(ParseFile::class, $saved->getMedia());
        $this->assertNotNull($saved->getMedia()->getURL());
        $this->assertSame($content, file_get_contents($saved->getMedia()->getURL()));
    }

    public function testTooLargeUploadIsRejectedBeforeBeingSaved(): void
    {
        $uploadedFile = $this->createUploadedFile(str_repeat('a', 2048), 'big.txt', 'text/plain');

        $picture = new Picture();
        $form = $this->submit($picture, $uploadedFile, [new ParseFileConstraint(maxSize: '1k')]);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('media')->getErrors());
        $this->assertStringContainsString('too large', $form->get('media')->getErrors()->current()->getMessage());
    }

    public function testUploadWithWrongMimeTypeIsRejected(): void
    {
        $uploadedFile = $this->createUploadedFile('plain text', 'notes.txt', 'text/plain');

        $form = $this->submit(new Picture(), $uploadedFile, [new ParseFileConstraint(mimeTypes: ['application/pdf'])]);

        $this->assertFalse($form->isValid());
        $this->assertStringContainsString('mime type', $form->get('media')->getErrors()->current()->getMessage());
    }

    public function testImageDimensionsAreValidatedOnUpload(): void
    {
        $png = base64_decode(self::PNG_2X2);

        $form = $this->submit(new Picture(), $this->createUploadedFile($png, 'small.png', 'image/png'), [new ParseFileImage(minWidth: 10)]);
        $this->assertFalse($form->isValid());
        $this->assertStringContainsString('width is too small', $form->get('media')->getErrors()->current()->getMessage());

        $form = $this->submit(new Picture(), $this->createUploadedFile($png, 'small.png', 'image/png'), [new ParseFileImage(maxWidth: 10)]);
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
    }

    public function testAlreadySavedFileIsNotValidatedAgain(): void
    {
        $picture = new Picture();
        $form = $this->submit($picture, $this->createUploadedFile(str_repeat('a', 2048), 'big.txt', 'text/plain'), []);
        $this->assertTrue($form->isValid());

        $this->om->persist($picture);
        $this->om->flush();
        $this->assertNotNull($picture->getMedia()->getURL());

        // Submitted again without any upload: the saved ParseFile is kept and a constraint the
        // stored file would break does not apply anymore.
        $form = $this->submit($picture, null, [new ParseFileConstraint(maxSize: '1k')]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertNotNull($picture->getMedia()->getURL());
    }

    public function testRegistryDoesNotOutliveTheParseFile(): void
    {
        $parseFile = ParseFile::createFromData('content', 'file.txt');
        $uploadedFile = $this->createUploadedFile('content', 'file.txt', 'text/plain');
        UploadedParseFileRegistry::attach($parseFile, $uploadedFile);
        $this->assertSame($uploadedFile, UploadedParseFileRegistry::get($parseFile));

        $uploadedFileRef = \WeakReference::create($uploadedFile);
        unset($uploadedFile, $parseFile);

        $this->assertNull($uploadedFileRef->get(), 'The UploadedFile must be released along with its ParseFile');
    }

    /**
     * @param \Symfony\Component\Validator\Constraint[] $constraints
     */
    private function submit(Picture $picture, ?UploadedFile $uploadedFile, array $constraints): FormInterface
    {
        $form = $this->factory->createNamedBuilder('picture', FormType::class, $picture, ['data_class' => Picture::class])
            ->add('file', TextType::class)
            ->add('media', ParseFileType::class, ['constraints' => $constraints])
            ->getForm()
        ;

        $request = Request::create(
            '/',
            'POST',
            ['picture' => ['file' => 'form-upload']],
            [],
            null === $uploadedFile ? [] : ['picture' => ['media' => $uploadedFile]],
        );
        $form->handleRequest($request);

        $this->assertTrue($form->isSubmitted());

        return $form;
    }

    private function createUploadedFile(string $content, string $clientName, string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'parse_upload_');
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;

        // test mode: the file did not go through PHP's upload handling (is_uploaded_file())
        return new UploadedFile($path, $clientName, $mimeType, null, true);
    }
}
