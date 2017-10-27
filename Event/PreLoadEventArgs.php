<?php

namespace Redking\ParseBundle\Event;

use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class that holds event arguments for a preLoad event.
 *
 * @since 1.0
 */
class PreLoadEventArgs extends LifecycleEventArgs
{
    /**
     * @var array
     */
    private $data;

    /**
     * Constructor.
     *
     * @param object          $object
     * @param ObjectManager   $om
     * @param array           $data     Array of data to be loaded and hydrated
     */
    public function __construct($object, ObjectManager $om, array &$data)
    {
        parent::__construct($object, $om);
        $this->data =& $data;
    }

    /**
     * Get the array of data to be loaded and hydrated.
     *
     * @return array
     */
    public function &getData()
    {
        return $this->data;
    }
}
