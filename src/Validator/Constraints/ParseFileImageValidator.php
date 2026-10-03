<?php

namespace Redking\ParseBundle\Validator\Constraints;

use Parse\ParseFile;
use Redking\ParseBundle\Form\UploadedParseFileRegistry;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ImageValidator;

class ParseFileImageValidator extends ImageValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof ParseFile) {
            parent::validate($value, $constraint);

            return;
        }

        // We only validate uploaded files
        $uploadedFile = UploadedParseFileRegistry::get($value);
        if (null == $value->getUrl() && null !== $uploadedFile) {
            parent::validate($uploadedFile, $constraint);
        }
    }
}
