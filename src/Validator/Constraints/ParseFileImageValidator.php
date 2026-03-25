<?php

namespace Redking\ParseBundle\Validator\Constraints;

use Parse\ParseFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ImageValidator;

class ParseFileImageValidator extends ImageValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$value instanceof ParseFile) {
            return parent::validate($value, $constraint);
        }

        // We only validate uploaded files
        if (null == $value->getUrl() && isset($value->_uploadedFile) && $value->_uploadedFile instanceof UploadedFile) {
            return parent::validate($value->_uploadedFile, $constraint);
        }
    }
}
