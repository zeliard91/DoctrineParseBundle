<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Redking\ParseBundle\Form;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\Common\Util\ClassUtils;
use Redking\ParseBundle\Types\Type;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Mapping\MappingException as LegacyMappingException;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

class DoctrineParseTypeGuesser implements FormTypeGuesserInterface
{
    protected $registry;

    private $cache = array();

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function guessType($class, $property)
    {
        if (!$ret = $this->getMetadata($class)) {
            return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', array(), Guess::LOW_CONFIDENCE);
        }

        list($metadata, $name) = $ret;

        if ($metadata->hasAssociation($property)) {
            $multiple = $metadata->isCollectionValuedAssociation($property);
            $mapping = $metadata->getAssociationMapping($property);

            return new TypeGuess('Redking\ParseBundle\Form\Type\ObjectType', array('em' => $name, 'class' => $mapping['targetDocument'], 'multiple' => $multiple), Guess::HIGH_CONFIDENCE);
        }

        switch ($metadata->getTypeOfField($property)) {
            case Type::TARRAY:
            case Type::HASH:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CollectionType', array(), Guess::MEDIUM_CONFIDENCE);
            case Type::BOOLEAN:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CheckboxType', array(), Guess::HIGH_CONFIDENCE);
            case 'vardatetime':
            case Type::DATE:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateType', array(), Guess::HIGH_CONFIDENCE);
            case Type::FLOAT:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\NumberType', array(), Guess::MEDIUM_CONFIDENCE);
            case Type::INTEGER:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\IntegerType', array(), Guess::MEDIUM_CONFIDENCE);
            case Type::STRING:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', array(), Guess::MEDIUM_CONFIDENCE);
            case Type::FILE:
                return new TypeGuess('Redking\ParseBundle\Form\Type\FileType', array(), Guess::HIGH_CONFIDENCE);
            case Type::TEXT:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextareaType', array(), Guess::MEDIUM_CONFIDENCE);
            default:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', array(), Guess::LOW_CONFIDENCE);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function guessRequired($class, $property)
    {
        $ret = $this->getMetadata($class);
        if ($ret && $ret[0]->hasField($property)) {
            if (!$ret[0]->isNullable($property)) {
                return new ValueGuess(
                    true,
                    Guess::HIGH_CONFIDENCE
                );
            }

            return new ValueGuess(
                false,
                Guess::MEDIUM_CONFIDENCE
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function guessMaxLength($class, $property)
    {
        $ret = $this->getMetadata($class);
        if ($ret && $ret[0]->hasField($property) && !$ret[0]->hasAssociation($property)) {
            $mapping = $ret[0]->getFieldMapping($property);

            if (isset($mapping['length'])) {
                return new ValueGuess($mapping['length'], Guess::HIGH_CONFIDENCE);
            }

            if (in_array($ret[0]->getTypeOfField($property), array(Type::FLOAT))) {
                return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function guessPattern($class, $property)
    {
        $ret = $this->getMetadata($class);
        if ($ret && $ret[0]->hasField($property) && !$ret[0]->hasAssociation($property)) {
            if (in_array($ret[0]->getTypeOfField($property), array(Type::FLOAT))) {
                return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }
    }

    protected function getMetadata($class)
    {
        // normalize class name
        $class = ClassUtils::getRealClass(ltrim($class, '\\'));

        if (array_key_exists($class, $this->cache)) {
            return $this->cache[$class];
        }

        $this->cache[$class] = null;
        foreach ($this->registry->getManagers() as $name => $em) {
            try {
                return $this->cache[$class] = array($em->getClassMetadata($class), $name);
            } catch (MappingException $e) {
                // not an entity or mapped super class
            } catch (LegacyMappingException $e) {
                // not an entity or mapped super class, using Doctrine ORM 2.2
            }
        }
    }
}
