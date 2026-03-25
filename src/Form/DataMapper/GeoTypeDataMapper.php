<?php

namespace Redking\ParseBundle\Form\DataMapper;

use Parse\ParseException;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;

class GeoTypeDataMapper extends DataMapper
{
    public function mapFormsToData(\Traversable $forms, mixed &$data): void
    {
        // If the mapping of latitude or longitude in ParseGeoPoint fails, the value is cleared.
        try {
            parent::mapFormsToData($forms, $data);
        } catch (ParseException $e) {
            $data = null;
        }
    }
}
