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

namespace Redking\ParseBundle;

/**
 * This factory is used to create default repository objects for entities at runtime.
 *
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 *
 * @since 2.4
 */
final class RepositoryFactory
{
    /**
     * The list of EntityRepository instances.
     *
     * @var \Doctrine\Persistence\ObjectRepository[]
     */
    private $repositoryList = array();

    /**
     * {@inheritdoc}
     */
    public function getRepository(ObjectManager $objectManager, $objectName)
    {
        $repositoryHash = $objectManager->getClassMetadata($objectName)->getName().spl_object_hash($objectManager);

        if (isset($this->repositoryList[$repositoryHash])) {
            return $this->repositoryList[$repositoryHash];
        }

        return $this->repositoryList[$repositoryHash] = $this->createRepository($objectManager, $objectName);
    }

    /**
     * Create a new repository instance for an object class.
     *
     * @param \Redking\ParseBundle\ObjectManager $objectManager The EntityManager instance.
     * @param string                             $objectName    The name of the object.
     *
     * @return \Doctrine\Persistence\ObjectRepository
     */
    private function createRepository(ObjectManager $objectManager, $objectName)
    {
        /* @var $metadata \Doctrine\ORM\Mapping\ClassMetadata */
        $metadata = $objectManager->getClassMetadata($objectName);
        $repositoryClassName = $metadata->customRepositoryClassName
            ?: '\Redking\ParseBundle\ObjectRepository';

        return new $repositoryClassName($objectManager, $metadata);
    }
}
