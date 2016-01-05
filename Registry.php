<?php

namespace Redking\ParseBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Redking\ParseBundle\Exception\RedkingParseException;

class Registry extends ManagerRegistry
{
    public function __construct(ContainerInterface $container, $manager_name)
    {
        $this->setContainer($container);
        parent::__construct('Parse', [], ['default' => $manager_name], null, 'default', 'Redking\ParseBundle\Proxy\Proxy');
    }
    /**
     * Resolves a registered namespace alias to the full namespace.
     *
     * This method looks for the alias in all registered entity managers.
     *
     * @param string $alias The alias
     *
     * @return string The full namespace
     *
     * @see Configuration::getEntityNamespace
     */
    public function getAliasNamespace($alias)
    {
        // dump($alias);die;
        foreach (array_keys($this->getManagers()) as $name) {
            try {
                return $this->getManager($name)->getConfiguration()->getEntityNamespace($alias);
            } catch (ORMException $e) {
            }
        }

        throw RedkingParseException::unknownObjectNamespace($alias);
    }
}
