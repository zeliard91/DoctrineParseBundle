<?php

namespace Redking\ParseBundle\Tests\Mapping\Driver;

use Redking\ParseBundle\Mapping\Driver\YamlDriver;

class YamlDriverTest extends AbstractDriverTestCase
{
    public function setUp(): void
    {
        if (!class_exists('Symfony\Component\Yaml\Yaml', true)) {
            $this->markTestSkipped('This test requires the Symfony YAML component');
        }

        $this->driver = new YamlDriver([__DIR__ . '/fixtures/yaml' => 'TestObjects']);
    }
}