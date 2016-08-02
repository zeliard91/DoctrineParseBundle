<?php


namespace Redking\ParseBundle\Validator\Constraints;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Constraint for the unique object validator
 *
 * @Annotation
 * @author Damien Matabon
 */
class Unique extends UniqueEntity
{
    public $service = 'doctrine_parse.unique';
}
