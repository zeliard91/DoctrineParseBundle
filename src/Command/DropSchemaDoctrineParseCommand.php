<?php

namespace Redking\ParseBundle\Command;

use Redking\ParseBundle\SchemaManager;
use Symfony\Component\Console\Input\InputInterface;

class DropSchemaDoctrineParseCommand extends SchemaDoctrineParseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:parse:schema:drop')
            ->setDescription('Drop the indexes declared in the mapping')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command drops the indexes declared in the mapping.

It never deletes a Parse class, a field or any data, and it never touches an index that
is absent from the mapping.

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
        return $schemaManager->deleteObjectIndexes($className, (bool) $input->getOption('dry-run'));
    }
}
