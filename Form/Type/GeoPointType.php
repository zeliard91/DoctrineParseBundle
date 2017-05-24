<?php

namespace Redking\ParseBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Parse\ParseGeoPoint;

class GeoPointType extends AbstractType
{

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('latitude', null, [
                'constraints' => [
                    new Assert\Range([
                        'min' => -90,
                        'max' => 90,
                    ]),
                ]
            ])
            ->add('longitude', null, [
                'constraints' => [
                    new Assert\Range([
                        'min' => -180,
                        'max' => 180,
                    ]),
                ]
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => ParseGeoPoint::class,
            'empty_data' => new ParseGeoPoint(0, 0)
        ));
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
    public function getBlockPrefix()
    {
        return 'geopoint';
    }
}
