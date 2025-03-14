<?php

namespace Redking\ParseBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Image;

/**
 * Constraint ParseFileImage validator
 *
 * @Annotation
 * @author Damien Matabon
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ParseFileImage extends Image
{

}
