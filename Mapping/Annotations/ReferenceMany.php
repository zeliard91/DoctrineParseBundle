<?php

namespace Redking\ParseBundle\Mapping\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Redking\ParseBundle\Mapping\ClassMetadata;

/**
 * Specifies a one-to-many relationship to a different document
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ReferenceMany extends AbstractField
{
    public $type = ClassMetadata::MANY;
    public $reference = true;
    public $lazyLoad = true;
    public $simple = false;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
    public $defaultDiscriminatorValue;
    public $orphanRemoval;
    public $inversedBy;
    public $mappedBy;
    public $repositoryMethod;
    public $sort = [];
    public $criteria = [];
    public $limit;
    public $skip;
    public $implementation = ClassMetadata::ASSOCIATION_IMPL_ARRAY;
    public $includeKeys;

    public function __construct(
        ?string $name = null,
        bool $nullable = false,
        ?string $targetDocument = null,
        ?string $discriminatorField = null,
        ?array $discriminatorMap = null,
        ?string $defaultDiscriminatorValue = null,
        public $cascade = null,
        ?bool $orphanRemoval = null,
        ?string $inversedBy = null,
        ?string $mappedBy = null,
        ?string $repositoryMethod = null,
        ?array $sort = [],
        ?array $criteria = [],
        ?int $limit = null,
        ?int $skip = null,
        ?string $implementation = ClassMetadata::ASSOCIATION_IMPL_ARRAY,
    ) {
        parent::__construct($name, ClassMetadata::MANY, $nullable);

        $this->targetDocument = $targetDocument;
        $this->discriminatorField = $discriminatorField;
        $this->discriminatorMap = $discriminatorMap;
        $this->defaultDiscriminatorValue = $defaultDiscriminatorValue;
        $this->inversedBy = $inversedBy;
        $this->orphanRemoval = $orphanRemoval;
        $this->mappedBy = $mappedBy;
        $this->repositoryMethod = $repositoryMethod;
        $this->sort = $sort;
        $this->criteria = $criteria;
        $this->limit = $limit;
        $this->skip = $skip;
        $this->implementation = $implementation;
    }
}
