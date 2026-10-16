<?php

namespace Redking\ParseBundle\Bridge\DataFixtures;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Loader;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fixtures loader that injects the service container into fixtures implementing
 * ContainerAwareInterface, replacing the Symfony one removed in Symfony 7.
 *
 * ContainerAwareInterface no longer exists in Symfony 7: fixtures are then loaded
 * as is and should rely on dependency injection instead.
 */
class ContainerAwareLoader extends Loader
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function addFixture(FixtureInterface $fixture): void
    {
        if ($fixture instanceof ContainerAwareInterface) {
            $fixture->setContainer($this->container);
        }

        parent::addFixture($fixture);
    }
}
