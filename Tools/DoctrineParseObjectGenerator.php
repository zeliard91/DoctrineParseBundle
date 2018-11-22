<?php

namespace Redking\ParseBundle\Tools;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Tools\Export\ClassMetadataExporter;

class DoctrineParseObjectGenerator extends Generator
{
    private $filesystem;
    private $registry;

    public function __construct(Filesystem $filesystem, ManagerRegistry $registry)
    {
        $this->filesystem = $filesystem;
        $this->registry = $registry;
    }

    public function generate(BundleInterface $bundle, $object, $format, array $fields, $withRepository, $updateExisting = false, $toStringField = null, $extends = null, $interfaces = [])
    {
        // configure the bundle (needed if the bundle does not contain any Entities yet)
        $config = $this->registry->getManager(null)->getConfiguration();
        $config->setEntityNamespaces(array_merge(
            array($bundle->getName() => $bundle->getNamespace().'\\ParseObject'),
            $config->getEntityNamespaces()
        ));

        $objectClass = $this->registry->getAliasNamespace($bundle->getName()).'\\'.$object;
        $objectPath = $bundle->getPath().'/ParseObject/'.str_replace('\\', '/', $object).'.php';

        $class = new ClassMetadata($objectClass);
        $class->setCollection($object);
        if ($withRepository) {
            $class->customRepositoryClassName = $objectClass.'Repository';
        }

        $class->mapField(array('fieldName' => 'id', 'type' => 'integer', 'id' => true));
        $class->mapField(array('fieldName' => 'createdAt', 'type' => 'date'));
        $class->mapField(array('fieldName' => 'updatedAt', 'type' => 'date'));
        foreach ($fields as $field) {
            $class->mapField($field);
        }

        $objectGenerator = $this->getObjectGenerator();
        if (null !== $extends) {
            $objectGenerator->setClassToExtend($extends);
        }
        $objectGenerator->setInterfacesToImplement($interfaces);
        
        $generateCodeUpdate = false;
        if (file_exists($objectPath)) {
            if (!$updateExisting) {
                throw new \RuntimeException(sprintf('Object "%s" already exists.', $objectClass));
            } else {
                $generateCodeUpdate = true;
            }
        }

        if ('annotation' === $format) {
            $objectGenerator->setGenerateAnnotations(true);
            if (!$generateCodeUpdate) {
                $objectCode = $objectGenerator->generateObjectClass($class, $toStringField);
            } else {
                $objectCode = $objectGenerator->generateUpdatedObjectClass($class, $objectPath, $toStringField);
            }
            $mappingPath = $mappingCode = false;
        } else {
            $cme = new ClassMetadataExporter();
            $exporter = $cme->getExporter('yml' == $format ? 'yaml' : $format);
            $mappingPath = $bundle->getPath().'/Resources/config/doctrine/'.str_replace('\\', '.', $object).'.parse.'.$format;

            if (file_exists($mappingPath)) {
                throw new \RuntimeException(sprintf('Cannot generate object when mapping "%s" already exists.', $mappingPath));
            }

            $mappingCode = $exporter->exportClassMetadata($class);
            $objectGenerator->setGenerateAnnotations(false);
            if (!$generateCodeUpdate) {
                $objectCode = $objectGenerator->generateObjectClass($class, $toStringField);
            } else {
                $objectCode = $objectGenerator->generateUpdatedObjectClass($class, $objectPath, $toStringField);
            }
        }

        $this->filesystem->mkdir(dirname($objectPath));
        file_put_contents($objectPath, $objectCode);

        if ($mappingPath) {
            $this->filesystem->mkdir(dirname($mappingPath));
            file_put_contents($mappingPath, $mappingCode);
        }

        if ($withRepository) {
            $path = $bundle->getPath().str_repeat('/..', substr_count(get_class($bundle), '\\'));
            $this->getRepositoryGenerator()->writeObjectRepositoryClass($class->customRepositoryClassName, $path);
        }
    }

    public function isReservedKeyword($keyword)
    {
        return $this->registry->getConnection()->getDatabasePlatform()->getReservedKeywordsList()->isKeyword($keyword);
    }

    protected function getObjectGenerator()
    {
        $objectGenerator = new ObjectGenerator();
        $objectGenerator->setGenerateAnnotations(false);
        $objectGenerator->setGenerateStubMethods(true);
        $objectGenerator->setregenerateObjectIfExists(false);
        $objectGenerator->setupdateObjectIfExists(true);
        $objectGenerator->setNumSpaces(4);
        $objectGenerator->setOverrideConstruct(true);
        $objectGenerator->setOverrideToString(true);
        // $objectGenerator->setAnnotationPrefix('ORM\\');

        return $objectGenerator;
    }

    protected function getRepositoryGenerator()
    {
        return new ObjectRepositoryGenerator();
    }
}
