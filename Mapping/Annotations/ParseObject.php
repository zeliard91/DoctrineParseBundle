<?php

namespace Redking\ParseBundle\Mapping\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * Identifies a class as a Parse object that can be stored in the database
 *
 * @Annotation
 */
class ParseObject extends Annotation
{
    public $collection;
    public $repositoryClass;
}