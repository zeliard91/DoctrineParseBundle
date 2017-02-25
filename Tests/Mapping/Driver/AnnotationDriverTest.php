<?php

namespace Redking\ParseBundle\Tests\Mapping\Driver;

use Doctrine\Common\Annotations\AnnotationReader;
use Redking\ParseBundle\Mapping\Driver\AnnotationDriver;

class AnnotationDriverTest extends AbstractDriverTest
{
    public function setUp()
    {
        AnnotationDriver::registerAnnotationClasses();

        $reader = new AnnotationReader();
        $this->driver = new AnnotationDriver($reader, [__DIR__ . '/fixtures' => 'TestObjects']);
    }
}