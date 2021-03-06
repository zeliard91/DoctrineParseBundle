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

use Doctrine\Common\DataFixtures\Loader;
use Redking\ParseBundle\Bridge\DataFixtures\Executor\ParseExecutor;
use Redking\ParseBundle\Bridge\DataFixtures\Purger\ParsePurger;
use Redking\ParseBundle\ObjectManager;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Load data fixtures from bundles.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class LoadDataFixturesDoctrineParseCommand extends DoctrineParseCommand
{
    /**
     * @var \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface
     */
    private $params;

    /**
     * @var \Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader
     */
    private $loader;

    public function __construct(ObjectManager $om, ParameterBagInterface $params, ContainerAwareLoader $loader)
    {
        parent::__construct($om);

        $this->params = $params;
        $this->loader = $loader;
    }

    /**
     * @return boolean
     */
    public function isEnabled()
    {
        return parent::isEnabled() && class_exists(Loader::class);
    }


    protected function configure()
    {
        $this
            ->setName('doctrine:parse:fixtures:load')
            ->setDescription('Load data fixtures to your database.')
            ->addOption('fixtures', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The directory or file to load data fixtures from.')
            ->addOption('bundles', 'b', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The bundles to load data fixtures from.')
            ->addOption('append', null, InputOption::VALUE_NONE, 'Append the data fixtures instead of flushing the database first.')
            ->setHelp(<<<EOT
The <info>doctrine:parse:fixtures:load</info> command loads data fixtures from your bundles:

  <info>./app/console doctrine:parse:fixtures:load</info>

You can also optionally specify the path to fixtures with the <info>--fixtures</info> option:

  <info>./app/console doctrine:parse:fixtures:load --fixtures=/path/to/fixtures1 --fixtures=/path/to/fixtures2</info>

If you want to append the fixtures instead of flushing the database first you can use the <info>--append</info> option:

  <info>./app/console doctrine:parse:fixtures:load --append</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $om = $this->getDoctrineParseManager();
        $kernel = $this->getApplication()->getKernel();

        $dirOrFile = $input->getOption('fixtures');
        $bundles = $input->getOption('bundles');
        if ($bundles && $dirOrFile) {
            throw new \InvalidArgumentException('Use only one option: --bundles or --fixtures.');
        }

        // if ($input->isInteractive() && !$input->getOption('append')) {
        //     $helper = $this->getHelper('question');
        //     $question = new ConfirmationQuestion('Careful, database will be purged. Do you want to continue (y/N) ?', false);

        //     if (! $helper->ask($input, $output, $question)) {
        //         return;
        //     }
        // }

        if ($dirOrFile) {
            $paths = is_array($dirOrFile) ? $dirOrFile : [$dirOrFile];
        } elseif ($bundles) {
            
            $paths = [$kernel->getRootDir().'/DataFixtures/Parse'];
            foreach ($bundles as $bundle) {
                $paths[] = $kernel->getBundle($bundle)->getPath();
            }
        } else {
            $paths = $this->params->get('doctrine_parse.fixtures_dirs');
            $paths = is_array($paths) ? $paths : [$paths];
            $paths[] = $kernel->getRootDir().'/DataFixtures/Parse';
            foreach ($kernel->getBundles() as $bundle) {
                $paths[] = $bundle->getPath().'/DataFixtures/Parse';
            }
        }

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->loader->loadFromDirectory($path);
            } else if (is_file($path)) {
                $this->loader->loadFromFile($path);
            }
        }

        $fixtures = $this->loader->getFixtures();
        if (!$fixtures) {
            throw new \InvalidArgumentException(
                sprintf('Could not find any fixtures to load in: %s', "\n\n- ".implode("\n- ", $paths))
            );
        }

        $purger = new ParsePurger($om);
        $executor = new ParseExecutor($om, $purger);
        $executor->setLogger(function($message) use ($output) {
            $output->writeln(sprintf('  <comment>></comment> <info>%s</info>', $message));
        });
        $executor->execute($fixtures, $input->getOption('append'));
    }
}
