<?php

namespace Redking\ParseBundle\Mapping\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Redking\ParseBundle\Mapping\ClassMetadata;

/**
 * Specifies a one-to-one relationship to a different document
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ReferenceOne extends AbstractField
{
    public $type = ClassMetadata::ONE;
    public $reference = true;
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

    public function __construct(
        ?string $name = null,
        bool $nullable = false,
        ?string $targetDocument = null,
        ?string $discriminatorField = null,
        ?array $discriminatorMap = null,
        public $cascade = null,
        ?bool $orphanRemoval = null,
        ?string $defaultDiscriminatorValue = null,
        ?string $inversedBy = null,
        ?string $mappedBy = null,
        ?string $repositoryMethod = null,
        ?array $sort = [],
        ?array $criteria = [],
        ?int $limit = null,
        ?int $skip = null,
    ) {
        parent::__construct($name, ClassMetadata::ONE, $nullable);

        $this->targetDocument = $targetDocument;
        $this->discriminatorField = $discriminatorField;
        $this->discriminatorMap = $discriminatorMap;
        $this->defaultDiscriminatorValue = $defaultDiscriminatorValue;
        $this->cascade = $cascade;
        $this->orphanRemoval = $orphanRemoval;
        $this->inversedBy = $inversedBy;
        $this->mappedBy = $mappedBy;
        $this->repositoryMethod = $repositoryMethod;
        $this->sort = $sort;
        $this->criteria = $criteria;
        $this->limit = $limit;
        $this->skip = $skip;
    }
}
