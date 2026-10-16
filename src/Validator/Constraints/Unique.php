<?php


namespace Redking\ParseBundle\Validator\Constraints;

use Attribute;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Attribute\HasNamedArguments;

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
    #[HasNamedArguments]
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
        // Named arguments: Symfony 7.1 inserted $identifierFieldNames before $groups.
        $arguments = [
            'fields' => $fields,
            'message' => $message,
            'service' => $service,
            'em' => $em,
            'entityClass' => $entityClass,
            'repositoryMethod' => $repositoryMethod,
            'errorPath' => $errorPath,
            'ignoreNull' => $ignoreNull,
            'groups' => $groups,
            'payload' => $payload,
        ];
        // Symfony 7.3+ deprecates any options array, only forward it when used.
        if ([] !== $options) {
            $arguments['options'] = $options;
        }

        parent::__construct(...$arguments);
    }
}
