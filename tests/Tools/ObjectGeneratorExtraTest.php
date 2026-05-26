<?php

namespace Redking\ParseBundle\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Tools\ObjectGenerator;

/**
 * Additional coverage for ObjectGenerator beyond the attribute-mode golden test:
 * annotation mode, configuration setters (spacing, parent class, interfaces,
 * traits), __toString, lifecycle callbacks, inheritance/discriminator output,
 * and the on-disk write/update flow.
 */
class ObjectGeneratorExtraTest extends TestCase
{
    private const FQCN = 'Redking\\ParseBundle\\Tests\\Generated\\Widget';

    /** @var string[] */
    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
            @rmdir($dir);
        }
        $this->tmpDirs = [];
    }

    private function tmpDir(): string
    {
        $dir = sys_get_temp_dir().'/redking_gen_'.uniqid('', true);
        mkdir($dir, 0775, true);
        $this->tmpDirs[] = $dir;

        return $dir;
    }

    private function widgetMetadata(): ClassMetadata
    {
        $cm = new ClassMetadata(self::FQCN);
        $cm->setCollection('Widget');

        $fields = [
            ['fieldName' => 'id', 'type' => 'string', 'id' => true],
            ['fieldName' => 'createdAt', 'type' => 'date'],
            ['fieldName' => 'updatedAt', 'type' => 'date'],
            ['fieldName' => 'name', 'type' => 'string', 'nullable' => false, 'name' => 'label'],
            ['fieldName' => 'count', 'type' => 'integer', 'nullable' => true],
            [
                'fieldName' => 'owner',
                'association' => ClassMetadata::REFERENCE_ONE,
                'type' => ClassMetadata::ONE,
                'targetDocument' => 'Redking\\ParseBundle\\Tests\\Models\\Blog\\User',
            ],
            [
                'fieldName' => 'tags',
                'association' => ClassMetadata::REFERENCE_MANY,
                'type' => ClassMetadata::MANY,
                'cascade' => 'persist',
                'targetDocument' => 'Redking\\ParseBundle\\Tests\\Models\\Blog\\Tag',
            ],
        ];
        foreach ($fields as $field) {
            $cm->mapField($field);
        }

        return $cm;
    }

    private function annotationGenerator(): ObjectGenerator
    {
        $generator = new ObjectGenerator();
        $generator->setGenerateAnnotations(true);
        $generator->setGenerateAttributes(false);
        $generator->setGenerateStubMethods(true);

        return $generator;
    }

    public function testAnnotationModeOutput(): void
    {
        $code = $this->annotationGenerator()->generateObjectClass($this->widgetMetadata());

        $this->assertStringContainsString('namespace Redking\\ParseBundle\\Tests\\Generated;', $code);
        $this->assertStringContainsString('use Redking\\ParseBundle\\Mapping\\Annotations as ORM;', $code);
        $this->assertStringContainsString('@ORM\\ParseObject', $code);
        $this->assertStringContainsString('collection="Widget"', $code);
        $this->assertStringContainsString('@ORM\\Id()', $code);
        $this->assertStringContainsString('@ORM\\Field(name="label", type="string")', $code);
        $this->assertStringContainsString('nullable=true', $code);
        $this->assertStringContainsString('@ORM\\ReferenceOne(targetDocument="Redking\\ParseBundle\\Tests\\Models\\Blog\\User")', $code);
        $this->assertStringContainsString('@ORM\\ReferenceMany(', $code);
        $this->assertStringContainsString('cascade={"persist"}', $code);
    }

    public function testStubMethodsAndConstructorAndTrait(): void
    {
        $code = $this->annotationGenerator()->generateObjectClass($this->widgetMetadata());

        $this->assertStringContainsString('public function getId(', $code);
        $this->assertStringContainsString('public function setName(', $code);
        $this->assertStringContainsString('public function getName(', $code);
        $this->assertStringContainsString('public function addTag(', $code);
        $this->assertStringContainsString('public function removeTag(', $code);
        $this->assertStringContainsString('public function getTags(', $code);
        $this->assertStringContainsString('new \\Doctrine\\Common\\Collections\\ArrayCollection()', $code);
        $this->assertStringContainsString('use \\Redking\\ParseBundle\\ACLTrait;', $code);
    }

    public function testToStringGeneration(): void
    {
        $code = $this->annotationGenerator()->generateObjectClass($this->widgetMetadata(), '$this->name');

        $this->assertStringContainsString('public function __toString()', $code);
        $this->assertStringContainsString('$this->name', $code);
    }

    public function testNumSpacesControlsIndentation(): void
    {
        $generator = $this->annotationGenerator();
        $generator->setNumSpaces(2);

        $code = $generator->generateObjectClass($this->widgetMetadata());

        $this->assertStringContainsString("\n  protected \$name", $code);
        $this->assertStringNotContainsString("\n    protected \$name", $code);
    }

    public function testClassToExtendAndInterfaces(): void
    {
        $generator = $this->annotationGenerator();
        $generator->setClassToExtend(\stdClass::class);
        $generator->setInterfacesToImplement(['\\Countable']);
        $generator->addInterfaceToImplement('\\ArrayAccess');

        $this->assertTrue($generator->hasInterfacesToImplement());
        $this->assertSame(['\\Countable', '\\ArrayAccess'], $generator->getInterfacesToImplement());

        $code = $generator->generateObjectClass($this->widgetMetadata());
        $this->assertStringContainsString('class Widget extends \\stdClass implements \\Countable, \\ArrayAccess', $code);
    }

    public function testAdditionnalTraits(): void
    {
        $generator = $this->annotationGenerator();
        $generator->setAdditionnalTraits(['\\Acme\\SomeTrait']);

        $this->assertSame(['\\Acme\\SomeTrait'], $generator->getAdditionnalTraits());
        $this->assertStringContainsString('use \\Acme\\SomeTrait;', $generator->generateObjectClass($this->widgetMetadata()));
    }

    public function testLifecycleCallbacksAreGenerated(): void
    {
        $cm = $this->widgetMetadata();
        $cm->addLifecycleCallback('onPrePersist', 'prePersist');

        $code = $this->annotationGenerator()->generateObjectClass($cm);

        $this->assertStringContainsString('@ORM\\HasLifecycleCallbacks', $code);
        $this->assertStringContainsString('public function onPrePersist()', $code);
        $this->assertStringContainsString('@ORM\\PrePersist', $code);
    }

    public function testInheritanceAndDiscriminatorAnnotations(): void
    {
        $cm = $this->widgetMetadata();
        $cm->setInheritanceType(ClassMetadata::INHERITANCE_TYPE_SINGLE_COLLECTION);
        $cm->setDiscriminatorField('kind');
        $cm->setDiscriminatorMap(['widget' => self::FQCN]);

        $code = $this->annotationGenerator()->generateObjectClass($cm);

        $this->assertStringContainsString('@ORM\\InheritanceType("SINGLE_COLLECTION")', $code);
        $this->assertStringContainsString('@ORM\\DiscriminatorField(name="kind")', $code);
        $this->assertStringContainsString('@ORM\\DiscriminatorMap({"widget" = "'.self::FQCN.'"})', $code);
    }

    public function testGenerateWritesClassToDisk(): void
    {
        $dir = $this->tmpDir();
        $generator = $this->annotationGenerator();
        $generator->setBackupExisting(false);

        $generator->generate([$this->widgetMetadata()], $dir);

        $expectedPath = $dir.'/Redking/ParseBundle/Tests/Generated/Widget.php';
        $this->assertFileExists($expectedPath);
        $this->assertStringContainsString('class Widget', file_get_contents($expectedPath));
    }

    public function testUpdateExistingFileKeepsCustomCodeAndBacksUp(): void
    {
        $dir = $this->tmpDir();
        $generator = $this->annotationGenerator();
        $generator->setBackupExisting(false);
        $generator->generate([$this->widgetMetadata()], $dir);

        $path = $dir.'/Redking/ParseBundle/Tests/Generated/Widget.php';

        // Inject a hand-written method, then re-run in update mode (with backup).
        $code = file_get_contents($path);
        $code = substr($code, 0, strrpos($code, '}'))
            ."    public function customBusinessMethod() { return 42; }\n}\n";
        file_put_contents($path, $code);

        $generator->setupdateObjectIfExists(true);
        $generator->setBackupExisting(true);
        $generator->writeObjectClass($this->widgetMetadata(), $dir);

        $updated = file_get_contents($path);
        $this->assertStringContainsString('customBusinessMethod', $updated, 'hand-written code must be preserved');
        $this->assertFileExists($path.'~', 'a backup file must be created in update mode');
    }

    public function testGenerateUpdatedObjectClassSplicesBody(): void
    {
        $dir = $this->tmpDir();
        $path = $dir.'/Existing.php';
        file_put_contents(
            $path,
            "<?php\n\nnamespace Foo;\n\nclass Existing\n{\n    public function kept() {}\n}\n"
        );

        $result = $this->annotationGenerator()->generateUpdatedObjectClass($this->widgetMetadata(), $path);

        $this->assertStringContainsString('public function kept()', $result);
        $this->assertStringContainsString('public function getName(', $result);
        $this->assertStringEndsWith("}\n", $result);
    }
}
