<?php

namespace Redking\ParseBundle\Form\DataMapper;

use Parse\ParseException;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;

class GeoTypeDataMapper extends PropertyPathMapper
{
    /**
     * {@inheritdoc}
     */
    public function mapFormsToData($forms, &$data)
    {
        // If the mapping of latitude or longitude in ParseGeoPoint fails, the value is cleared.
        try {
            parent::mapFormsToData($forms, $data);
        } catch (ParseException $e) {
            $data = null;
        }
    }
}
