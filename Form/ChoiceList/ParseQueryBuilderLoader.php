<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Redking\ParseBundle\Form\ChoiceList;

use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Redking\ParseBundle\QueryBuilder;
use Doctrine\Persistence\ObjectManager;

/**
 * Loads entities using a {@link QueryBuilder} instance.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ParseQueryBuilderLoader implements EntityLoaderInterface
{
    /**
     * Contains the query builder that builds the query for fetching the
     * entities.
     *
     * This property should only be accessed through queryBuilder.
     *
     * @var QueryBuilder
     */
    private $queryBuilder;

    /**
     * Construct an ORM Query Builder Loader.
     *
     * @param QueryBuilder|\Closure $queryBuilder The query builder or a closure
     *                                            for creating the query builder.
     *                                            Passing a closure is
     *                                            deprecated and will not be
     *                                            supported anymore as of
     *                                            Symfony 3.0.
     * @param ObjectManager         $manager      Deprecated.
     * @param string                $class        Deprecated.
     *
     * @throws UnexpectedTypeException
     */
    public function __construct($queryBuilder, $manager = null, $class = null)
    {
        // If a query builder was passed, it must be a closure or QueryBuilder
        // instance
        if (!($queryBuilder instanceof QueryBuilder || $queryBuilder instanceof \Closure)) {
            throw new UnexpectedTypeException($queryBuilder, 'Redking\ParseBundle\QueryBuilder or \Closure');
        }

        if ($queryBuilder instanceof \Closure) {
            @trigger_error('Passing a QueryBuilder closure to '.__CLASS__.'::__construct() is deprecated since version 2.7 and will be removed in 3.0.', E_USER_DEPRECATED);

            if (!$manager instanceof ObjectManager) {
                throw new UnexpectedTypeException($manager, 'Doctrine\Persistence\ObjectManager');
            }

            @trigger_error('Passing an EntityManager to '.__CLASS__.'::__construct() is deprecated since version 2.7 and will be removed in 3.0.', E_USER_DEPRECATED);
            @trigger_error('Passing a class to '.__CLASS__.'::__construct() is deprecated since version 2.7 and will be removed in 3.0.', E_USER_DEPRECATED);

            $queryBuilder = $queryBuilder($manager->getRepository($class));

            if (!$queryBuilder instanceof QueryBuilder) {
                throw new UnexpectedTypeException($queryBuilder, 'Redking\ParseBundle\QueryBuilder');
            }
        }

        $this->queryBuilder = $queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntities(): array
    {
        return $this->queryBuilder->getQuery()->execute()->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getEntitiesByIds(string $identifier, array $values): array
    {
        if (empty($values) || (count($values) == 1 && $values[0] === '')) {
            return [];
        }

        $qb = clone $this->queryBuilder;

        return array_values($qb
            ->field($identifier)->in($values)
            ->getQuery()
            ->execute()->toArray());
    }
}
