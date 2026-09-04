<?php

namespace Redking\ParseBundle\Command;

use Redking\ParseBundle\SchemaManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class CreateSchemaDoctrineParseCommand extends SchemaDoctrineParseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:parse:schema:create')
            ->setDescription('Create the indexes declared in the mapping')
            ->addOption('no-fields', null, InputOption::VALUE_NONE, 'Do not create the missing fields of the Parse classes.')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command creates the classes, the missing fields and the
indexes declared in the mapping. It is purely additive: nothing is ever dropped, and an
index whose keys differ from the mapping is only reported, so that
<info>doctrine:parse:schema:update</info> can recreate it.

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
        return $schemaManager->ensureObjectIndexes(
            $className,
            !$input->getOption('no-fields'),
            (bool) $input->getOption('dry-run')
        );
    }
}
