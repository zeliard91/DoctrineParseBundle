<?php

/*
 * This file is part of the Doctrine MongoDBBundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Redking\ParseBundle\Command;

use Redking\ParseBundle\Tools\DisconnectedClassMetadataFactory;
use Redking\ParseBundle\Tools\ObjectGenerator;
use Redking\ParseBundle\Tools\Command\Helper\ObjectManagerHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Base class for Doctrine ODM console commands to extend.
 *
 * @author Justin Hileman <justin@justinhileman.info>
 */
abstract class DoctrineParseCommand extends ContainerAwareCommand
{
    public static function setApplicationObjectManager(Application $application)
    {
        $om = $application->getKernel()->getContainer()->get('redking_parse.manager');
        $helperSet = $application->getHelperSet();
        $helperSet->set(new ObjectManagerHelper($om), 'om');
    }

    protected function getObjectGenerator()
    {
        $documentGenerator = new ObjectGenerator();
        $documentGenerator->setGenerateAnnotations(false);
        $documentGenerator->setGenerateStubMethods(true);
        $documentGenerator->setRegenerateObjectIfExists(false);
        $documentGenerator->setUpdateObjectIfExists(true);
        $documentGenerator->setNumSpaces(4);

        return $documentGenerator;
    }

    protected function getDoctrineParseManager()
    {
        return $this->getContainer()->get('redking_parse.manager');
    }

    protected function getBundleMetadatas(Bundle $bundle)
    {
        $namespace = $bundle->getNamespace();
        $bundleMetadatas = array();
        $om = $this->getDoctrineParseManager();

        $cmf = new DisconnectedClassMetadataFactory();
        $cmf->setObjectManager($om);
        // $cmf->setConfiguration($om->getConfiguration());
        $metadatas = $cmf->getAllMetadata();
        foreach ($metadatas as $metadata) {
            if (strpos($metadata->name, $namespace) === 0) {
                $bundleMetadatas[$metadata->name] = $metadata;
            }
        }

        return $bundleMetadatas;
    }

    protected function findBundle($bundleName)
    {
        $foundBundle = false;
        foreach ($this->getApplication()->getKernel()->getBundles() as $bundle) {
            /* @var $bundle Bundle */
            if (strtolower($bundleName) == strtolower($bundle->getName())) {
                $foundBundle = $bundle;
                break;
            }
        }

        if (!$foundBundle) {
            throw new \InvalidArgumentException('No bundle '.$bundleName.' was found.');
        }

        return $foundBundle;
    }

    /**
     * Transform classname to a path $foundBundle substract it to get the destination.
     *
     * @param Bundle $bundle
     *
     * @return string
     */
    protected function findBasePathForBundle($bundle)
    {
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $bundle->getNamespace());
        $search = str_replace('\\', DIRECTORY_SEPARATOR, $bundle->getPath());
        $destination = str_replace(DIRECTORY_SEPARATOR.$path, '', $search, $c);
        // if ($c != 1) {
        //     throw new \RuntimeException(sprintf('Can\'t find base path for bundle (path: "%s", destination: "%s").', $path, $destination));
        // }
        return $destination;
    }
}
