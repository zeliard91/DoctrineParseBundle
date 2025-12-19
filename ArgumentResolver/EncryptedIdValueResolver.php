<?php

namespace Redking\ParseBundle\ArgumentResolver;

use Redking\ParseBundle\Attribute\EncryptedId;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\Security\EncryptionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves controller arguments marked with the EncryptedId attribute
 *
 * This resolver:
 * 1. Detects route parameters marked with #[EncryptedId]
 * 2. Decrypts the parameter value using EncryptionService
 * 3. Loads the corresponding Parse object from the database
 * 4. Returns the entity instance for injection into the controller
 *
 * @see EncryptedId
 */
class EncryptedIdValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ObjectManager $om,
        private readonly EncryptionService $encryptionService
    ) {}

    /**
     * @return iterable<object>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        // Check if the argument has the EncryptedId attribute
        $attributes = $argument->getAttributes(EncryptedId::class, ArgumentMetadata::IS_INSTANCEOF);
        if (empty($attributes)) {
            return [];
        }

        // Get the encrypted value from the route
        $encryptedId = $request->attributes->get($argument->getName());
        if (!$encryptedId) {
            return [];
        }

        /** @var EncryptedId $attribute */
        $attribute = $attributes[0];

        // Decrypt the ID
        $id = $this->encryptionService->decrypt($encryptedId);

        if (!$id) {
            throw new NotFoundHttpException('Invalid encrypted ID.');
        }

        // Load the entity
        $entity = $this->om->getRepository($attribute->class)->find($id);

        if (!$entity) {
            throw new NotFoundHttpException(
                sprintf('No entity "%s" found for ID "%s".', $attribute->class, $id)
            );
        }

        return [$entity];
    }
}
