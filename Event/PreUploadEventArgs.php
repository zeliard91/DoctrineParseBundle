<?php

namespace Redking\ParseBundle\Event;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Parse\ParseFile;

/**
 * Lifecycle Events are triggered by the form type ParseFileType when upload is made
 */
class PreUploadEventArgs extends LifecycleEventArgs
{
    /**
     * 
     * @var UploadedFile
     */
    private $uploadedFile;

    /**
     * Name of the field in the ParseObject.
     *
     * @var string
     */
    private $fieldName;

    /**
     * ParseFile about to be uploaded.
     *
     * @var \Parse\ParseFile
     */
    private $parseFile;

    /**
     * @param object        $object
     * @param ObjectManager $objectManager
     * @param UploadedFile  $uploadedFile
     * @param UploadedFile  $parseFile
     * @param string        $fieldName
     */
    public function __construct($object, ObjectManager $objectManager, UploadedFile $uploadedFile, $fieldName, ParseFile $parseFile)
    {
        parent::__construct($object, $objectManager);

        $this->uploadedFile = $uploadedFile;
        $this->fieldName = $fieldName;
        $this->parseFile = $parseFile;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @return UploadedFile
     */
    public function getUploadedFile()
    {
        return $this->uploadedFile;
    }

    /**
     * @return \Parse\ParseFile
     */
    public function getParseFile()
    {
        return $this->parseFile;
    }
}
