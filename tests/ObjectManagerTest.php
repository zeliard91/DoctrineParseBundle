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

    public function dataMethodsAffectedByNoObjectArguments()
    {
        return array(
            array('persist'),
            array('remove'),
            array('merge'),
            array('refresh'),
            array('detach')
        );
    }
    /**
     * @dataProvider dataMethodsAffectedByNoObjectArguments
     * @expectedException \InvalidArgumentException
     * @param string $methodName
     */
    public function testThrowsExceptionOnNonObjectValues($methodName)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->om->$methodName(null);
    }
    public function dataAffectedByErrorIfClosedException()
    {
        return array(
            array('flush'),
            array('persist'),
            array('remove'),
            array('merge'),
            array('refresh'),
        );
    }
}