<?php

namespace Redking\ParseBundle\Hydrator;

use Parse\ParseACL;
use Parse\ParseObject;
use Redking\ParseBundle\Event\LifecycleEventArgs;
use Redking\ParseBundle\Event\PreLoadEventArgs;
use Redking\ParseBundle\Events;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\PersistentCollection;
use Redking\ParseBundle\Types\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\EventManager;

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

    /**
     * @var EventManager
     */
    private $evm;

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
                } elseif ($mapping['type'] === Type::ENCRYPTED_STRING) {
                    // Decrypt encrypted string field
                    $type = \Redking\ParseBundle\Types\Type::getType(Type::ENCRYPTED_STRING);
                    $decrypted = $type->convertToPHPValue($data->get($mapping['name']));
                    $this->class->reflFields[$key]->setValue($object, $decrypted);
                } else {
                    $this->class->reflFields[$key]->setValue($object, $data->get($mapping['name']));
                }
            }
            // reset value if doctrine refresh
            elseif (isset($hints['doctrine.refresh'])
                && !in_array($key, ['id', 'createdAt', 'updatedAt'])
                && !isset($mapping['reference'])
                && null !== $this->class->reflFields[$key]->getValue($object)) {
                $this->class->reflFields[$key]->setValue($object, null);
            }
        }

        $this->preRegisterFullyLoadedAssociations($data, $hints);

        // load associations
        foreach ($this->class->associationMappings as $field => $assoc) {
            $targetClass = $this->om->getClassMetadata($assoc['targetDocument']);
            switch (true) {
                
                // load referenceOne
                case ($assoc['type'] === ClassMetadata::ONE):
                    if (!$assoc['isOwningSide']) {
                        if ($assoc['fetch'] === ClassMetadata::FETCH_LAZY) {
                            // Create a lazy proxy: the query runs only on first property access
                            $reflField = $this->class->reflFields[$field];
                            $proxy = $this->om->getProxyFactory()->getLazyReferenceOneProxy(
                                $assoc['targetDocument'],
                                $assoc['mappedBy'],
                                $object,
                                $reflField
                            );
                            $reflField->setValue($object, $proxy);
                        } else {
                            // FETCH_EAGER: existing behaviour
                            $targetObject = $this->om->getUnitOfWork()
                                ->getObjectPersister($assoc['targetDocument'])
                                ->loadReference($assoc['mappedBy'], $object);

                            if (null !== $targetObject) {
                                $this->class->reflFields[$field]->setValue($object, $targetObject);
                            }
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
                        // $this->loadCollection($pColl);
                        $pColl->takeSnapshot();
                    }

                    // Try to hydrate loaded collection if available
                    try {
                        $references = $data->get($assoc['name']);
                        if (is_array($references)) {
                            // Track whether the whole array is fully included: only
                            // then can we flag the collection initialized and skip a
                            // later lazy reload. A reload reads the owner's original
                            // ParseObject, which is gone once the owner is detached
                            // (e.g. under 'doctrine.do_not_manage'), so an included
                            // collection left uninitialized would fatally fail on
                            // first access.
                            $fullyIncluded = true;
                            foreach ($references as $reference) {
                                if ($reference instanceof ParseObject && $reference->isDataAvailable()) {
                                    $pColl->add($this->om->getUnitOfWork()->getOrCreateObject($assoc['targetDocument'], $reference, $hints));
                                } elseif ($reference instanceof ParseObject) {
                                    $fullyIncluded = false;
                                }
                            }
                            $pColl->takeSnapshot();
                            if ($fullyIncluded) {
                                $pColl->setInitialized(true);
                            }
                        }
                    } catch (\Exception $e) {
                        // do nothing as the key has not been fetched
                    }

                    break;
            }
        }

        // Load ACLs
        if (method_exists($object, 'getPublicAcl')) {
            $acl = $data->getAcl();
            if (null !== $acl) {
                $object->setPublicAcl($acl->getPublicReadAccess(), $acl->getPublicWriteAccess());
                $encoded = $acl->_encode();
                if (is_array($encoded)) {
                    foreach ($encoded as $key => $permissions) {
                        if ($key !== ParseACL::PUBLIC_KEY) {
                            if (preg_match('/^role\:(\w+)$/', $key, $match) === 1) {
                                $object->addRoleAcl($match[1], isset($permissions['read']), isset($permissions['write']));
                            } else {
                                $object->addUserAcl($key, isset($permissions['read']), isset($permissions['write']));
                            }
                        }
                    }
                }
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

    /**
     * Pre-register a concrete (non-proxy) instance in the identity map for every
     * association whose payload is fully available, BEFORE the association-loading
     * loop below recurses into nested hydration.
     *
     * Without this, a Pointer field nested inside a sibling include is resolved
     * first via getReference() and locks a generated proxy class into the identity
     * map; the top-level full payload that arrives next then only re-hydrates the
     * proxy in place, leaving the caller with a __CG__\... proxy class for the
     * second include even though the data is fully loaded.
     *
     * Skipped under 'doctrine.do_not_manage': that hint makes getOrCreateObject()
     * return early (before hydrate()), so a pre-registered instance would stay
     * empty yet keep the full ParseObject as its change-detection baseline — the
     * next flush would then compute a "full -> null" changeset and wipe the row.
     */
    private function preRegisterFullyLoadedAssociations(\Parse\ParseObject $data, array $hints): void
    {
        if (isset($hints['doctrine.do_not_manage'])) {
            return;
        }

        $uow = $this->om->getUnitOfWork();
        foreach ($this->class->associationMappings as $assoc) {
            $targetClass = $this->om->getClassMetadata($assoc['targetDocument']);
            $rootName    = $targetClass->rootEntityName;

            if ($assoc['type'] === ClassMetadata::ONE) {
                $ref = $data->get($assoc['name']);
                if ($ref instanceof ParseObject && $ref->isDataAvailable()) {
                    $this->preRegisterReference($uow, $targetClass, $rootName, $ref);
                }
                continue;
            }

            try {
                $refs = $data->get($assoc['name']);
            } catch (\Exception $e) {
                continue;
            }
            if (!is_array($refs)) {
                continue;
            }
            foreach ($refs as $ref) {
                if ($ref instanceof ParseObject && $ref->isDataAvailable()) {
                    $this->preRegisterReference($uow, $targetClass, $rootName, $ref);
                }
            }
        }
    }

    /**
     * Register a concrete managed instance for a fully-available Pointer payload,
     * unless that id is already tracked by the UnitOfWork.
     */
    private function preRegisterReference($uow, ClassMetadata $targetClass, string $rootName, ParseObject $ref): void
    {
        $refId = $ref->getObjectId();
        if ($refId !== null && $uow->tryGetById($refId, $rootName) === false) {
            $uow->registerManaged($targetClass->newInstance(), $refId, $ref);
        }
    }
}
