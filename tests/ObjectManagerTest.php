<?php

namespace Redking\ParseBundle\Tests;

class ObjectManagerTest extends TestCase
{
    public function testGetConfiguration()
    {
        $this->assertInstanceOf('\Redking\ParseBundle\Configuration', $this->om->getConfiguration());
    }

    public function testGetUnitOfWork()
    {
        $this->assertInstanceOf('\Redking\ParseBundle\UnitOfWork', $this->om->getUnitOfWork());
    }

    public static function dataMethodsAffectedByNoObjectArguments(): array
    {
        return [
            ['persist'],
            ['remove'],
            ['merge'],
            ['refresh'],
            ['detach'],
        ];
    }

    /**
     * @dataProvider dataMethodsAffectedByNoObjectArguments
     */
    public function testThrowsExceptionOnNonObjectValues(string $methodName)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->om->$methodName(null);
    }

    public static function dataAffectedByErrorIfClosedException(): array
    {
        return [
            ['flush'],
            ['persist'],
            ['remove'],
            ['merge'],
            ['refresh'],
        ];
    }
}