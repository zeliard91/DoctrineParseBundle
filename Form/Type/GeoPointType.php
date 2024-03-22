<?php

namespace Redking\ParseBundle\Form\Type;

use Parse\ParseGeoPoint;
use Redking\ParseBundle\Form\DataMapper\GeoTypeDataMapper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class GeoPointType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper(new GeoTypeDataMapper());
        $builder
            ->add('latitude', NumberType::class, [
                'scale' => 6,
                'constraints' => [
                    new Assert\Range([
                        'min' => -90,
                        'max' => 90,
                    ]),
                ]
            ])
            ->add('longitude', NumberType::class, [
                'scale' => 6,
                'constraints' => [
                    new Assert\Range([
                        'min' => -180,
                        'max' => 180,
                    ]),
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => ParseGeoPoint::class,
            'empty_data' => new ParseGeoPoint(0, 0),
        ));
    }

    public function getName(): string
    {
        return $this->getBlockPrefix();
    }

    public function getBlockPrefix(): string
    {
        return 'geopoint';
    }
}
