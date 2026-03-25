<?php

namespace Redking\ParseBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraints\File;

/**
 * Constraint ParseFile validator
 *
 * @Annotation
 * @author Damien Matabon
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ParseFile extends File
{

}
