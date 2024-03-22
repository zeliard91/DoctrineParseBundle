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

class CreateProxyDirectoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('doctrine.parse.proxy_dir')) {
            return;
        }

        // Don't do anything if auto_generate_proxy_classes is false
        if (! $container->getParameter('doctrine.parse.auto_generate_proxy_classes')) {
            return;
        }

        // Create document proxy directory
        $proxyCacheDir = (string) $container->getParameter('doctrine.parse.proxy_dir');
        if (! is_dir($proxyCacheDir)) {
            if (@mkdir($proxyCacheDir, 0775, true) === false && ! is_dir($proxyCacheDir)) {
                throw new RuntimeException(
                    sprintf('Unable to create the Doctrine Proxy directory (%s)', dirname($proxyCacheDir))
                );
            }
        } elseif (! is_writable($proxyCacheDir)) {
            throw new RuntimeException(
                sprintf('Unable to write in the Doctrine Proxy directory (%s)', $proxyCacheDir)
            );
        }
    }
}
