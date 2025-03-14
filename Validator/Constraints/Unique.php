<?php


namespace Redking\ParseBundle\Validator\Constraints;

use Attribute;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Constraint for the unique object validator
 *
 * @Annotation
 * @author Damien Matabon
 * @Target({"CLASS", "ANNOTATION"})
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Unique extends UniqueEntity
{
    public function __construct(
        array|string $fields,
        ?string $message = null,
        string $service = 'doctrine_parse.unique',
        ?string $em = null,
        ?string $entityClass = null,
        ?string $repositoryMethod = 'findByWithoutManaging',
        ?string $errorPath = null,
        bool|array|string|null $ignoreNull = null,
        ?array $groups = null,
        mixed $payload = null,
        array $options = [],
    ) {
        parent::__construct(
            $fields,
            $message,
            $service,
            $em,
            $entityClass,
            $repositoryMethod,
            $errorPath,
            $ignoreNull,
            $groups,
            $payload,
            $options,
        );
    }
}
