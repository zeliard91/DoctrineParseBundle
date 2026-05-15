<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Redking\ParseBundle\Form\ChoiceList;

use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;
use Redking\ParseBundle\QueryBuilder;

/**
 * Loads entities using a {@link QueryBuilder} instance.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ParseQueryBuilderLoader implements EntityLoaderInterface
{
    public function __construct(private QueryBuilder $queryBuilder)
    {
    }

    public function getEntities(): array
    {
        return $this->queryBuilder->getQuery()->execute()->toArray();
    }

    public function getEntitiesByIds(string $identifier, array $values): array
    {
        if (empty($values) || (count($values) === 1 && $values[0] === '')) {
            return [];
        }

        $qb = clone $this->queryBuilder;

        return array_values($qb
            ->field($identifier)->in($values)
            ->getQuery()
            ->execute()
            ->toArray());
    }
}
