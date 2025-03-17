<?php

namespace Redking\ParseBundle\Mapping\Annotations;

abstract class AbstractField implements Annotation
{
    /** @var string|null */
    public $name;

    /** @var string|null */
    public $type = 'string';

    /** @var bool */
    public $nullable = false;

    public function __construct(
        ?string $name = null,
        ?string $type = null,
        bool $nullable = false
    )
    {
        $this->name = $name;
        $this->type = $type;
        $this->nullable = $nullable;
    }
}
