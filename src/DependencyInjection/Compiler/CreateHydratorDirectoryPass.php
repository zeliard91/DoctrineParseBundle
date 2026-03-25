<?php

declare(strict_types=1);

namespace Redking\ParseBundle\DependencyInjection\Compiler;

use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function dirname;
use function is_dir;
use function is_writable;
use function mkdir;
use function sprintf;

class CreateHydratorDirectoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('doctrine.parse.hydrator_dir')) {
            return;
        }

        // Don't do anything if auto_generate_hydrator_classes is false
        if (! $container->getParameter('doctrine.parse.auto_generate_hydrator_classes')) {
            return;
        }

        // Create document proxy directory
        $hydratorCacheDir = (string) $container->getParameter('doctrine.parse.hydrator_dir');
        if (! is_dir($hydratorCacheDir)) {
            if (@mkdir($hydratorCacheDir, 0775, true) === false && ! is_dir($hydratorCacheDir)) {
                throw new RuntimeException(
                    sprintf('Unable to create the Doctrine Hydrator directory (%s)', dirname($hydratorCacheDir))
                );
            }
        } elseif (! is_writable($hydratorCacheDir)) {
            throw new RuntimeException(
                sprintf('Unable to write in the Doctrine Hydrator directory (%s)', $hydratorCacheDir)
            );
        }
    }
}
