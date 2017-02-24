<?php

namespace Redking\ParseBundle\Mapping\Annotations;

/** @Annotation */
final class ReferenceOne extends AbstractField
{
    public $type = 'one';
    public $reference = true;
    public $simple = false;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
    public $defaultDiscriminatorValue;
    public $cascade;
    public $orphanRemoval;
    public $inversedBy;
    public $mappedBy;
    public $repositoryMethod;
    public $sort = array();
    public $criteria = array();
    public $limit;
    public $skip;
}
