<?php

namespace Redking\ParseBundle\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Tools\ObjectGenerator;

class ObjectGeneratorTest extends TestCase
{
    public function testGenerateObject()
    {
        $this->assertTrue(true);

        $class = new ClassMetadata('Redking\\ParseBundle\\Tests\\Models\\TestGeneration');
        $class
            ->setCollection('TestGeneration')
        ;

        $fields = [];
        $fields[] = ['fieldName' => 'id', 'type' => 'string', 'id' => true];
        $fields[] = ['fieldName' => 'createdAt', 'type' => 'date'];
        $fields[] = ['fieldName' => 'updatedAt', 'type' => 'date'];
        $fields[] = ['fieldName' => 'name', 'type' => 'string', 'nullable' => false, 'name' => 'label'];
        $fields[] = ['fieldName' => 'location', 'type' => 'geopoint'];
        $fields[] = ['fieldName' => 'isActive', 'type' => 'boolean', 'default' => false];
        $fields[] = ['fieldName' => 'backup', 'type' => 'file'];
        $fields[] = [
            'fieldName' => 'pictures',
            'association' => ClassMetadata::REFERENCE_MANY,
            'type' => ClassMetadata::MANY,
            'cascade' => 'persist',
            'targetDocument' => 'Redking\\ParseBundle\\Tests\\Models\\Blog\\Picture',
        ];
        $fields[] = [
            'fieldName' => 'owner',
            'association' => ClassMetadata::REFERENCE_ONE,
            'type' => ClassMetadata::ONE,
            'targetDocument' => 'Redking\\ParseBundle\\Tests\\Models\\Blog\\User',
        ];

        foreach ($fields as $field) {
            $class->mapField($field);
        }

        $generator = new ObjectGenerator();

        $generator->setGenerateAnnotations(false);
        $generator->setGenerateAttributes(true);
        $generator->setGenerateStubMethods(true);

        $toStringField = '$this->name';
        $generatedCode = $generator->generateObjectClass($class, $toStringField);
        
        $expectedCode = file_get_contents(realpath(__DIR__ . '/../Models/Blog/TestGeneration.php'));
        $this->assertEquals($expectedCode, $generatedCode);
    }
}