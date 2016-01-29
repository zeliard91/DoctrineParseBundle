<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Redking\ParseBundle\Tools;

use Redking\ParseBundle\Event\LoadClassMetadataEventArgs;
use Redking\ParseBundle\Event\OnClassMetadataNotFoundEventArgs;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Doctrine\Common\EventSubscriber;
use Redking\ParseBundle\Events;

/**
 * ResolveTargetObjectListener
 *
 * Mechanism to overwrite interfaces or classes specified as association
 * targets.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @since 2.2
 */
class ResolveTargetObjectListener implements EventSubscriber
{
    /**
     * @var array[] indexed by original object name
     */
    private $resolveTargetObjects = array();

    /**
     * {@inheritDoc}
     */
    public function getSubscribedEvents()
    {
        return array(
            Events::loadClassMetadata,
            Events::onClassMetadataNotFound
        );
    }

    /**
     * Adds a target-object class name to resolve to a new class name.
     *
     * @param string $originalObject
     * @param string $newObject
     * @param array  $mapping
     *
     * @return void
     */
    public function addResolveObject($originalObject, $newObject, array $mapping)
    {
        $mapping['targetDocument'] = ltrim($newObject, "\\");
        $this->resolveTargetObjects[ltrim($originalObject, "\\")] = $mapping;
    }

    /**
     * @param OnClassMetadataNotFoundEventArgs $args
     *
     * @internal this is an event callback, and should not be called directly
     *
     * @return void
     */
    public function onClassMetadataNotFound(OnClassMetadataNotFoundEventArgs $args)
    {
        if (array_key_exists($args->getClassName(), $this->resolveTargetObjects)) {
            $args->setFoundMetadata(
                $args
                    ->getObjectManager()
                    ->getClassMetadata($this->resolveTargetObjects[$args->getClassname()]['targetDocument'])
            );
        }
    }

    /**
     * Processes event and resolves new target object names.
     *
     * @param LoadClassMetadataEventArgs $args
     *
     * @return void
     *
     * @internal this is an event callback, and should not be called directly
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $args)
    {
        /* @var $cm \Redking\ParseBundle\Mapping\ClassMetadata */
        $cm = $args->getClassMetadata();

        foreach ($cm->associationMappings as $mapping) {
            if (isset($this->resolveTargetObjects[$mapping['targetDocument']])) {
                $this->remapAssociation($cm, $mapping);
            }
        }

        foreach ($this->resolveTargetObjects as $interface => $data) {
            if ($data['targetDocument'] == $cm->getName()) {
                $args->getObjectManager()->getMetadataFactory()->setMetadataFor($interface, $cm);
            }
        }
    }

    /**
     * @param \Redking\ParseBundle\Mapping\ClassMetadataInfo $classMetadata
     * @param array                                   $mapping
     *
     * @return void
     */
    private function remapAssociation($classMetadata, $mapping)
    {
        $newMapping = $this->resolveTargetObjects[$mapping['targetDocument']];
        $newMapping = array_replace_recursive($mapping, $newMapping);
        $newMapping['fieldName'] = $mapping['fieldName'];

        unset($classMetadata->fieldMappings[$mapping['fieldName']]);
        unset($classMetadata->associationMappings[$mapping['fieldName']]);

        switch ($mapping['type']) {
            case ClassMetadata::MANY:
                $classMetadata->mapToMany($newMapping);
                break;
            case ClassMetadata::ONE:
                $classMetadata->mapToOne($newMapping);
                break;
        }
    }
}
