<?php

namespace Redking\ParseBundle\Mapping\Annotations;

/** @Annotation */
final class Id extends AbstractField
{
    public $id = true;
    public $type = 'string';
}
