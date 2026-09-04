<?php

namespace Redking\ParseBundle\Command;

use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\SchemaManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shared behaviour of the doctrine:parse:schema:* commands.
 */
abstract class SchemaDoctrineParseCommand extends DoctrineParseCommand
{
    /**
     * Runs the action on a single class and returns the report of the SchemaManager.
     *
     * @return array<string, mixed>
     */
    abstract protected function processObject(SchemaManager $schemaManager, string $className, InputInterface $input): array;

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('class', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Restrict the command to these object classes (FQCN).')
            ->addOption('index', 'i', InputOption::VALUE_NONE, 'Only process the indexes.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without writing anything.')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $objectManager = $this->getDoctrineParseManager();
        $schemaManager = $objectManager->getSchemaManager();
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $output->writeln('<comment>Dry run: nothing will be written to Parse.</comment>');
            $output->writeln('');
        }

        $classNames = $input->getOption('class');

        if (!$classNames) {
            $classNames = [];
            foreach ($objectManager->getMetadataFactory()->getAllMetadata() as $class) {
                assert($class instanceof ClassMetadata);

                if ($class->isMappedSuperclass || $class->isEmbeddedDocument) {
                    continue;
                }

                $classNames[] = $class->name;
            }
        }

        $failure = false;

        foreach ($classNames as $className) {
            try {
                $this->renderReport($output, $className, $this->processObject($schemaManager, $className, $input));
            } catch (\Exception $e) {
                $output->writeln(sprintf('<error>[FAIL]</error> %s', $className));
                $output->writeln(sprintf('<comment>%s</comment>', $e->getMessage()));

                $failure = true;
            }
        }

        return $failure ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function renderReport(OutputInterface $output, string $className, array $report): void
    {
        if ($report['skipped'] !== null) {
            if ($output->isVerbose()) {
                $output->writeln(sprintf('<comment>[SKIP]</comment> %s (%s)', $className, $report['skipped']));
            }

            return;
        }

        $output->writeln(sprintf(
            '<info>[OK]</info>   %s: %d created, %d dropped, %d unchanged, %d field(s) added',
            $className,
            count($report['create']),
            count($report['dropped']),
            count($report['unchanged']),
            count($report['fields'])
        ));

        foreach ($report['create'] as $name => $spec) {
            $output->writeln(sprintf('        + index %s %s', $name, $this->formatSpec($spec)));
        }

        foreach ($report['dropped'] as $name) {
            $output->writeln(sprintf('        - index %s', $name));
        }

        foreach ($report['fields'] as $name => $definition) {
            $output->writeln(sprintf('        + field %s (%s)', $name, $definition['type']));
        }

        foreach ($report['mismatched'] as $name => $change) {
            $output->writeln(sprintf(
                '        <comment>! index %s differs: %s expected, %s found. Run doctrine:parse:schema:update to recreate it.</comment>',
                $name,
                $this->formatSpec($change['to']),
                $this->formatSpec($change['from'])
            ));
        }

        if (!$output->isVerbose()) {
            return;
        }

        foreach ($report['unchanged'] as $name) {
            $output->writeln(sprintf('        = index %s', $name));
        }

        foreach ($report['unmanaged'] as $name) {
            $output->writeln(sprintf('        ~ index %s is not mapped and was left untouched', $name));
        }
    }

    /**
     * @param array<string, int|string> $spec
     */
    private function formatSpec(array $spec): string
    {
        $parts = [];

        foreach ($spec as $column => $order) {
            $parts[] = sprintf('%s: %s', $column, $order);
        }

        return '{'.implode(', ', $parts).'}';
    }
}
