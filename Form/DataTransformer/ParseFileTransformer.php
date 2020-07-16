<?php

namespace Redking\ParseBundle\Form\DataTransformer;

use Parse\ParseFile;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

class ParseFileTransformer implements DataTransformerInterface
{
    private $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    /**
     * Transforms an object (parseFile) to a string (number).
     *
     * @param  Issue|null $parseFile
     * @return string
     */
    public function transform($parseFile)
    {
        return $parseFile;
    }

    /**
     * Transforms a string (number) to an object (parseFile).
     *
     * @param  string $parseFileNumber
     * @return Issue|null
     * @throws TransformationFailedException if object (parseFile) is not found.
     */
    public function reverseTransform($uploadedFile)
    {
        if ($uploadedFile instanceof UploadedFile) {
            if ($this->options['force_name'] !== false && $this->options['force_name'] !== '') {
                $fileName = $this->options['force_name'];
            } elseif (true === $this->options['autocorrect_name']) {
                $fileName = (new AsciiSlugger())->slug(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $uploadedFile->getClientOriginalExtension();
            } else {
                $fileName = $uploadedFile->getClientOriginalName();
                $fileName = str_replace(' ', '-', $fileName);
                if (preg_match("/^[_a-zA-Z0-9][a-zA-Z0-9@\.\ ~_-]*$/", $fileName) !== 1) {
                    throw new TransformationFailedException('Unable to save file : filename contains invalid characters.');
                }
            }

            $parseFile = ParseFile::createFromFile($uploadedFile->getPathname(), $fileName);
            // Attach UploadedFile to the created ParseFile so it can be used by validators
            $parseFile->_uploadedFile = $uploadedFile;

            return $parseFile;
        }

        return $uploadedFile;
    }
}
