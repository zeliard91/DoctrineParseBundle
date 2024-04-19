<?php

namespace Redking\ParseBundle\Tests\Form\Type;

use Parse\ParseGeoPoint;
use Redking\ParseBundle\Form\Type\GeoPointType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class GeoPointTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidPoint()
    {
        $formData = [
            'latitude' => 48.250,
            'longitude' => 1.99,
        ];

        $model = new ParseGeoPoint(0, 0);

        $form = $this->factory->create(GeoPointType::class, $model);

        $expected = new ParseGeoPoint($formData['latitude'], $formData['longitude']);

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($expected, $model);
    }
}