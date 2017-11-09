<?php

namespace Redking\ParseBundle\Mapping\Annotations;

use Redking\ParseBundle\Mapping\ClassMetadata;

/** @Annotation */
final class ReferenceMany extends AbstractField
{
    public $type = 'many';
    public $reference = true;
    public $lazyLoad = true;
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
    public $implementation = ClassMetadata::ASSOCIATION_IMPL_ARRAY;
}
