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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generate document classes from mapping information.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateObjectsDoctrineParseCommand extends DoctrineParseCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:parse:generate:objects')
            ->setDescription('Generate object classes and method stubs from your mapping information.')
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle to initialize the object or objects in.')
            ->addOption('object', null, InputOption::VALUE_OPTIONAL, 'The object class to initialize (shortname without namespace).')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Do not backup existing entities files.')
            ->setHelp(<<<EOT
The <info>doctrine:parse:generate:objects</info> command generates object classes and method stubs from your mapping information:

You have to limit generation of objects to an individual bundle:

  <info>php app/console doctrine:parse:generate:objects MyCustomBundle</info>

Alternatively, you can limit generation to a single object within a bundle:

  <info>php app/console doctrine:parse:generate:objects "MyCustomBundle" --object="User"</info>

You have to specify the shortname (without namespace) of the object you want to filter for.

By default, the unmodified version of each object is backed up and saved
(e.g. ~Product.php). To prevent this task from creating the backup file,
pass the <comment>--no-backup</comment> option:

  <info>php app/console doctrine:parse:generate:objects MyCustomBundle --no-backup</info>
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundleName = $input->getArgument('bundle');
        $filterObject = $input->getOption('object');

        $foundBundle = $this->findBundle($bundleName);

        if ($metadatas = $this->getBundleMetadatas($foundBundle)) {
            $output->writeln(sprintf('Generating objects for "<info>%s</info>"', $foundBundle->getName()));
            $documentGenerator = $this->getObjectGenerator();
            $documentGenerator->setBackupExisting(!$input->getOption('no-backup'));

            foreach ($metadatas as $metadata) {
                if ($filterObject && $metadata->getReflectionClass()->getShortName() != $filterObject) {
                    continue;
                }

                if (strpos($metadata->name, $foundBundle->getNamespace()) === false) {
                    throw new \RuntimeException(
                        'Object '.$metadata->name." and bundle don't have a common namespace, ".
                        'generation failed because the target directory cannot be detected.'
                    );
                }

                $output->writeln(sprintf('  > generating <comment>%s</comment>', $metadata->name));
                $documentGenerator->generate(array($metadata), $this->findBasePathForBundle($foundBundle));
            }
        } else {
            throw new \RuntimeException(
                'Bundle '.$bundleName.' does not contain any mapped objects.'.
                'Did you maybe forget to define a mapping configuration?'
            );
        }
    }
}
