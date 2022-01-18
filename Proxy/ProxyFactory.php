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

namespace Redking\ParseBundle\Proxy;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\Common\Proxy\ProxyDefinition;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Common\Proxy\Proxy as BaseProxy;
use Doctrine\Common\Proxy\ProxyGenerator;
// use Doctrine\ORM\ORMInvalidArgumentException;
use Redking\ParseBundle\Exception\ParseObjectNotFoundException;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\Persisters\ObjectPersister;


/**
 * This factory is used to create proxy objects for entities at runtime.
 *
 * @author Roman Borschel <roman@code-factory.org>
 * @author Giorgio Sironi <piccoloprincipeazzurro@gmail.com>
 * @author Marco Pivetta  <ocramius@gmail.com>
 *
 * @since 2.0
 */
class ProxyFactory extends AbstractProxyFactory
{
    /**
     * @var \Redking\ParseBundle\ObjectManager The ObjectManager this factory is bound to.
     */
    private $om;

    /**
     * @var \Doctrine\ORM\UnitOfWork The UnitOfWork this factory uses to retrieve persisters
     */
    private $uow;

    /**
     * @var string
     */
    private $proxyNs;

    /**
     * Initializes a new instance of the <tt>ProxyFactory</tt> class that is
     * connected to the given <tt>ObjectManager</tt>.
     *
     * @param \Redking\ParseBundle\ObjectManager $om           The ObjectManager the new factory works for.
     * @param string                             $proxyDir     The directory to use for the proxy classes. It must exist.
     * @param string                             $proxyNs      The namespace to use for the proxy classes.
     * @param bool                               $autoGenerate Whether to automatically generate proxy classes.
     */
    public function __construct(ObjectManager $om, $proxyDir, $proxyNs, $autoGenerate = false)
    {
        $proxyGenerator = new ProxyGenerator($proxyDir, $proxyNs);

        $proxyGenerator->setPlaceholder('baseProxyInterface', 'Redking\ParseBundle\Proxy\Proxy');
        parent::__construct($proxyGenerator, $om->getMetadataFactory(), $autoGenerate);

        $this->om = $om;
        $this->uow = $om->getUnitOfWork();
        $this->proxyNs = $proxyNs;
    }

    /**
     * {@inheritdoc}
     */
    protected function skipClass(ClassMetadata $metadata)
    {
        /* @var $metadata \Doctrine\ORM\Mapping\ClassMetadataInfo */
        return $metadata->isMappedSuperclass || $metadata->getReflectionClass()->isAbstract();
    }

    /**
     * {@inheritdoc}
     */
    protected function createProxyDefinition($className)
    {
        $classMetadata = $this->om->getClassMetadata($className);
        $objectPersister = $this->uow->getObjectPersister($className);

        return new ProxyDefinition(
            ClassUtils::generateProxyClassName($className, $this->proxyNs),
            $classMetadata->getIdentifierFieldNames(),
            $classMetadata->getReflectionProperties(),
            $this->createInitializer($classMetadata, $objectPersister),
            $this->createCloner($classMetadata, $objectPersister)
        );
    }

    /**
     * Creates a closure capable of initializing a proxy.
     *
     * @param \Doctrine\Persistence\Mapping\ClassMetadata $classMetadata
     * @param \Redking\ParseBundle\Persisters\ObjectPersister    $objectPersister
     *
     * @return \Closure
     *
     * @throws \Redking\ParseBundle\Exception\ParseObjectNotFoundException
     */
    private function createInitializer(ClassMetadata $classMetadata, ObjectPersister $objectPersister)
    {
        if ($classMetadata->getReflectionClass()->hasMethod('__wakeup')) {
            return function (BaseProxy $proxy) use ($objectPersister, $classMetadata) {
                $initializer = $proxy->__getInitializer();
                $cloner = $proxy->__getCloner();

                $proxy->__setInitializer(null);
                $proxy->__setCloner(null);

                if ($proxy->__isInitialized()) {
                    return;
                }

                $properties = $proxy->__getLazyProperties();

                foreach ($properties as $propertyName => $property) {
                    if (!isset($proxy->$propertyName)) {
                        $proxy->$propertyName = $properties[$propertyName];
                    }
                }

                $proxy->__setInitialized(true);
                $proxy->__wakeup();

                if (null === $objectPersister->load($classMetadata->getIdentifierValues($proxy), $proxy, null, ['doctrine.refresh' => true])) {
                    $proxy->__setInitializer($initializer);
                    $proxy->__setCloner($cloner);
                    $proxy->__setInitialized(false);

                    throw ParseObjectNotFoundException::objectNotFound(get_class($proxy), $classMetadata->getIdentifierValues($proxy));
                }
            };
        }

        return function (BaseProxy $proxy) use ($objectPersister, $classMetadata) {
            $initializer = $proxy->__getInitializer();
            $cloner = $proxy->__getCloner();

            $proxy->__setInitializer(null);
            $proxy->__setCloner(null);

            if ($proxy->__isInitialized()) {
                return;
            }

            $properties = $proxy->__getLazyProperties();

            foreach ($properties as $propertyName => $property) {
                if (!isset($proxy->$propertyName)) {
                    $proxy->$propertyName = $properties[$propertyName];
                }
            }

            $proxy->__setInitialized(true);

            if (null === $objectPersister->load($classMetadata->getIdentifierValues($proxy), $proxy, null, ['doctrine.refresh' => true])) {
                $proxy->__setInitializer($initializer);
                $proxy->__setCloner($cloner);
                $proxy->__setInitialized(false);

                throw ParseObjectNotFoundException::objectNotFound(get_class($proxy), $classMetadata->getIdentifierValues($proxy));
            }
        };
    }

    /**
     * Creates a closure capable of finalizing state a cloned proxy.
     *
     * @param \Doctrine\Persistence\Mapping\ClassMetadata $classMetadata
     * @param \Redking\ParseBundle\Persisters\ObjectPersister    $objectPersister
     *
     * @return \Closure
     *
     * @throws \Redking\ParseBundle\Exception\ParseObjectNotFoundException
     */
    private function createCloner(ClassMetadata $classMetadata, ObjectPersister $objectPersister)
    {
        return function (BaseProxy $proxy) use ($objectPersister, $classMetadata) {
            if ($proxy->__isInitialized()) {
                return;
            }

            $proxy->__setInitialized(true);
            $proxy->__setInitializer(null);
            $class = $objectPersister->getClassMetadata();
            $original = $objectPersister->load($classMetadata->getIdentifierValues($proxy));

            if (null === $original) {
                throw ParseObjectNotFoundException::objectNotFound(get_class($proxy), $classMetadata->getIdentifierValues($proxy));
            }

            foreach ($class->getReflectionClass()->getProperties() as $reflectionProperty) {
                $propertyName = $reflectionProperty->getName();

                if ($class->hasField($propertyName) || $class->hasAssociation($propertyName)) {
                    $reflectionProperty->setAccessible(true);
                    $reflectionProperty->setValue($proxy, $reflectionProperty->getValue($original));
                }
            }
        };
    }
}
