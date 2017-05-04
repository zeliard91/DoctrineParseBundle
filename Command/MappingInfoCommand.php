<?php

namespace Redking\ParseBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class MappingInfoCommand extends DoctrineParseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('doctrine:parse:mapping:info')
            ->setDescription('Show basic information about all mapped parse objects')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $objectManager = $this->getDoctrineParseManager();

        $objectClassNames = $objectManager->getConfiguration()
                                          ->getMetadataDriverImpl()
                                          ->getAllClassNames();

        if (!$objectClassNames) {
            throw new \Exception(
                'You do not have any mapped Doctrine Parse objects according to the current configuration. '.
                'If you have objects or mapping files you should check your mapping configuration for errors.'
            );
        }

        $output->writeln(sprintf("Found <info>%d</info> mapped objects:", count($objectClassNames)));

        $failure = false;

        foreach ($objectClassNames as $objectClassName) {
            try {
                $objectManager->getClassMetadata($objectClassName);
                $output->writeln(sprintf("<info>[OK]</info>   %s", $objectClassName));
            } catch (MappingException $e) {
                $output->writeln("<error>[FAIL]</error> ".$objectClassName);
                $output->writeln(sprintf("<comment>%s</comment>", $e->getMessage()));
                $output->writeln('');

                $failure = true;
            }
        }

        return $failure ? 1 : 0;
    }
}
