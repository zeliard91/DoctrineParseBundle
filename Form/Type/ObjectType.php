<?php

namespace Redking\ParseBundle\Form\Type;

use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Form\Type\DoctrineType;
use Redking\ParseBundle\QueryBuilder;
use Redking\ParseBundle\Form\ChoiceList\ParseQueryBuilderLoader;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ObjectType extends DoctrineType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        // Invoke the query builder closure so that we can cache choice lists
        // for equal query builders
        $queryBuilderNormalizer = function (Options $options, $queryBuilder) {
            if (is_callable($queryBuilder)) {
                $queryBuilder = call_user_func($queryBuilder, $options['em']->getRepository($options['class']));

                if (null !== $queryBuilder && !$queryBuilder instanceof QueryBuilder) {
                    throw new UnexpectedTypeException($queryBuilder, 'Redking\ParseBundle\QueryBuilder');
                }
            }

            return $queryBuilder;
        };

        $resolver->setNormalizer('query_builder', $queryBuilderNormalizer);
        $resolver->setAllowedTypes('query_builder', array('null', 'callable', 'Redking\ParseBundle\QueryBuilder'));
    }

    /**
     * Return the default loader object.
     *
     * @param ObjectManager $manager
     * @param object  $queryBuilder
     * @param string        $class
     *
     * @return ParseQueryBuilderLoader
     */
    public function getLoader(ObjectManager $manager, object $queryBuilder, string $class): ParseQueryBuilderLoader
    {
        return new ParseQueryBuilderLoader($queryBuilder, $manager, $class);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'object';
    }

    /**
     * We consider two query builders with an equal SQL string and
     * equal parameters to be equal.
     *
     * @param QueryBuilder $queryBuilder
     *
     * @return array
     *
     */
    public function getQueryBuilderPartsForCachingHash($queryBuilder): ?array
    {
        return array(
                $queryBuilder->getQuery()->toArray(),
        );
    }
}
