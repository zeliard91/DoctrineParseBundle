<?php

namespace Redking\ParseBundle\Tests\Mapping\Driver;

use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Mapping\Driver\YamlDriver;
use Redking\ParseBundle\Mapping\MappingException;

class YamlDriverTest extends AbstractDriverTestCase
{
    public function setUp(): void
    {
        if (!class_exists('Symfony\Component\Yaml\Yaml', true)) {
            $this->markTestSkipped('This test requires the Symfony YAML component');
        }

        $this->driver = new YamlDriver([__DIR__ . '/fixtures/yaml' => 'TestObjects']);
    }

    public function testClassLevelAndFieldLevelIndexes()
    {
        require_once __DIR__ . '/fixtures/IndexedUser.php';

        $classMetadata = new ClassMetadata('\TestObjects\IndexedUser');
        $this->driver->loadMetadataForClass('TestObjects\IndexedUser', $classMetadata);

        $this->assertEquals([
            ['keys' => ['username' => 1, 'address' => -1], 'options' => ['name' => 'username_address']],
            ['keys' => ['email' => 1], 'options' => []],
            ['keys' => ['username' => 1], 'options' => []],
            ['keys' => ['email' => -1], 'options' => ['name' => 'email_desc']],
        ], $classMetadata->getIndexes());

        $this->assertArrayNotHasKey(
            'index',
            $classMetadata->fieldMappings['username'],
            'the index declaration is not part of the field mapping'
        );
    }

    /**
     * @dataProvider provideUnsupportedFieldOptions
     */
    public function testAnUnsupportedFieldLevelIndexFlagIsRejected($option)
    {
        $classMetadata = new ClassMetadata('\\TestObjects\\IndexedUser');

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf('Index option "%s"', $option));

        $driver = new YamlDriver([__DIR__ . '/fixtures/yaml' => 'TestObjects']);
        $reflection = new \ReflectionMethod($driver, 'addFieldMapping');
        $reflection->invoke($driver, $classMetadata, [
            'fieldName' => 'username',
            'type' => 'string',
            $option => true,
        ]);
    }

    public static function provideUnsupportedFieldOptions()
    {
        return [['unique'], ['sparse']];
    }

    public function testAnUnsupportedFieldIndexOptionIsRejected()
    {
        $classMetadata = new ClassMetadata('\TestObjects\IndexedUser');

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Index option "unique"');

        $driver = new YamlDriver([__DIR__ . '/fixtures/yaml' => 'TestObjects']);
        $reflection = new \ReflectionMethod($driver, 'addFieldMapping');
        $reflection->invoke($driver, $classMetadata, [
            'fieldName' => 'username',
            'type' => 'string',
            'index' => ['unique' => true],
        ]);
    }
}