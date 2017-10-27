<?php

namespace Redking\ParseBundle\Hydrator;

use Redking\ParseBundle\Event\LifecycleEventArgs;
use Redking\ParseBundle\Event\PreLoadEventArgs;
use Redking\ParseBundle\Events;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\PersistentCollection;
use Redking\ParseBundle\Types\Type;
use Doctrine\Common\Collections\ArrayCollection;

class ParseObjectHydrator
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var ClassMetadata
     */
    private $class;

    public function __construct(ObjectManager $om, ClassMetadata $class)
    {
        $this->om = $om;
        $this->class = $class;
        $this->evm = $this->om->getEventManager();
    }

    /**
     * Hydrate object.
     *
     * @param object             $object
     * @param \Parse\ParseObject $data   [description]
     * @param array              $hints  [description]
     *
     * @return [type] [description]
     */
    public function hydrate($object, \Parse\ParseObject $data, array $hints)
    {
        $metadata = $this->om->getClassMetadata(get_class($object));
        // Invoke preLoad lifecycle events and listeners
        if ( ! empty($metadata->lifecycleCallbacks[Events::preLoad])) {
            $args = array(&$data);
            $metadata->invokeLifecycleCallbacks(Events::preLoad, $object, $args);
        }
        if ($this->evm->hasListeners(Events::preLoad)) {
            $this->evm->dispatchEvent(Events::preLoad, new PreLoadEventArgs($object, $this->om, $data));
        }

        $this->class->reflFields['id']->setValue($object, $data->getObjectId());
        $this->class->reflFields['createdAt']->setValue($object, $data->getCreatedAt());
        $this->class->reflFields['updatedAt']->setValue($object, $data->getUpdatedAt());
        foreach ($this->class->fieldMappings as $key => $mapping) {
            if ($data->has($mapping['name']) && !isset($mapping['reference'])) {
                if ($mapping['type'] === Type::GEOPOINT && null !== $data->get($mapping['name'])) {
                    $this->class->reflFields[$key]->setValue($object, clone $data->get($mapping['name']));
                } else {
                    $this->class->reflFields[$key]->setValue($object, $data->get($mapping['name']));
                }
            }
        }

        // load associations
        foreach ($this->class->associationMappings as $field => $assoc) {
            $targetClass = $this->om->getClassMetadata($assoc['targetDocument']);
            switch (true) {
                
                // load referenceOne
                case ($assoc['type'] === ClassMetadata::ONE):
                    if ( ! $assoc['isOwningSide']) {

                        // Try to load the owning side
                        $targetObject = $this->om->getUnitOfWork()->getObjectPersister($assoc['targetDocument'])->loadReference($assoc['mappedBy'], $object);

                        if (null !== $targetObject) {
                            $this->class->reflFields[$field]->setValue($object, $targetObject);
                        }
                    }

                    // Get object or set Proxy
                    $reference_parse = $data->get($assoc['name']);
                    if (is_object($reference_parse)) {
                        if ($reference_parse->isDataAvailable()) {
                            $reference = $this->om->getUnitOfWork()->getOrCreateObject($assoc['targetDocument'], $reference_parse, $hints);
                        } else {
                            $reference = $this->om->getReference($assoc['targetDocument'], $reference_parse->getObjectId(), $data->get($assoc['name']));
                        }
                        $this->class->reflFields[$field]->setValue($object, $reference);
                    }

                    break;

                // load referenceMany
                default:
                    // Inject collection
                    
                    $pColl = new PersistentCollection($this->om, $targetClass, new ArrayCollection);
                    $pColl->setOwner($object, $assoc);
                    $pColl->setInitialized(false);

                    $reflField = $this->class->reflFields[$field];
                    $reflField->setValue($object, $pColl);

                    if ($assoc['fetch'] == ClassMetadata::FETCH_EAGER) {
                        $this->loadCollection($pColl);
                        $pColl->takeSnapshot();
                    }

                    // $this->om->getUnitOfWork()->originalObjectData[$oid][$field] = $pColl;

                    break;
            }
        }

        // Invoke the postLoad lifecycle callbacks and listeners
        if ( ! empty($metadata->lifecycleCallbacks[Events::postLoad])) {
            $metadata->invokeLifecycleCallbacks(Events::postLoad, $object);
        }
        if ($this->evm->hasListeners(Events::postLoad)) {
            $this->evm->dispatchEvent(Events::postLoad, new LifecycleEventArgs($object, $this->om));
        }
    }
}
