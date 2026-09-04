<?php

namespace Redking\ParseBundle\Command;

use Redking\ParseBundle\SchemaManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class UpdateSchemaDoctrineParseCommand extends SchemaDoctrineParseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:parse:schema:update')
            ->setDescription('Synchronize the indexes of the Parse classes with the mapping')
            ->addOption('no-fields', null, InputOption::VALUE_NONE, 'Do not create the missing fields of the Parse classes.')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command creates the classes, the missing fields and the
indexes declared in the mapping, and recreates the mapped indexes whose keys changed.

An index that is not declared in the mapping is never dropped.

Beware: recreating an index takes two requests, a drop then a create. If the second one
fails, the index stays dropped and the command has to be run again.

Parse requires a master key for every schema operation.
EOT
            )
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function processObject(SchemaManager $schemaManager, string $className, InputInterface $input): array
    {
        return $schemaManager->updateObjectIndexes(
            $className,
            !$input->getOption('no-fields'),
            (bool) $input->getOption('dry-run')
        );
    }
}
