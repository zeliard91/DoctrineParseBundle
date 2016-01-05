<?php

namespace Redking\ParseBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Parse\ParseFile;

class ParseFileType extends FileType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($options) {
                $object = $event->getData();
                $form = $event->getForm();

                // Transform UploadedFile into ParseFile
                if ($object instanceof UploadedFile) {
                    if ($options['force_name'] !== false && $options['force_name'] !== '') {
                        $fileName = $options['force_name'];
                    } else {
                        $fileName = $object->getClientOriginalName();
                    }
                    $object = ParseFile::createFromFile($object->getPathname(), $fileName);
                    $event->setData($object);
                } // reset ParseFile if widget has not been filled
                elseif (null === $object && $form->getData() instanceof ParseFile) {
                    $event->setData($form->getData());
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => ParseFile::class,
            'force_name' => false,
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
        return 'parse_file';
    }
}
