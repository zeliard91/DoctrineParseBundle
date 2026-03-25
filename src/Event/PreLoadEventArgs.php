<?php

namespace Redking\ParseBundle\Event;

use Doctrine\Persistence\ObjectManager;
use Parse\ParseObject;

/**
 * Class that holds event arguments for a preLoad event.
 *
 * @since 1.0
 */
class PreLoadEventArgs extends LifecycleEventArgs
{
    /**
     * @var ParseObject
     */
    private $data;

    /**
     * Constructor.
     *
     * @param object          $object
     * @param ObjectManager   $om
     * @param ParseObject     $data     Data to be loaded and hydrated
     */
    public function __construct($object, ObjectManager $om, ParseObject $data)
    {
        parent::__construct($object, $om);
        $this->data = $data;
    }

    /**
     * Get the object to be loaded and hydrated.
     *
     * @return ParseObject
     */
    public function getData()
    {
        return $this->data;
    }
}
