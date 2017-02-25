<?php

namespace Redking\ParseBundle\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation;

abstract class AbstractField extends Annotation
{
    public $name;
    public $type = 'string';
    public $nullable = false;
}
