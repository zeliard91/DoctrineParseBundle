<?php

namespace Redking\ParseBundle\Form\Type;

use Parse\ParseFile;
use Redking\ParseBundle\Event\ListenersInvoker;
use Redking\ParseBundle\Event\PreUploadEventArgs;
use Redking\ParseBundle\Exception\WrappedParseException;
use Redking\ParseBundle\Events;
use Redking\ParseBundle\ObjectManager;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParseFileType extends FileType
{
    /**
     * @var \Redking\ParseBundle\ObjectManager
     */
    private $om;

    /**
     * @param Registry $registry Doctrine Parse Registry
     */
    public function __construct(ObjectManager $om)
    {
        $this->om = $om;
    }

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
                        $fileName = str_replace(' ', '-', $fileName);
                        if (preg_match("/^[_a-zA-Z0-9][a-zA-Z0-9@\.\ ~_-]*$/", $fileName) !== 1) {
                            $form->addError(new FormError('Filename contains invalid characters.'));
                            return;
                        }
                    }

                    $parseFile = ParseFile::createFromFile($object->getPathname(), $fileName);
                    $event->setData($parseFile);

                    // Dispatch preUpload Event on parent ParseObject
                    $class = $this->om->getClassMetadata($form->getParent()->getConfig()->getDataClass());
                    $parent = $form->getParent()->getData();

                    $listenersInvoker = $this->om->getUnitOfWork()->getListenersInvoker();
                    $invoke = $listenersInvoker->getSubscribedSystems($class, Events::preUpload);

                    if ($invoke !== ListenersInvoker::INVOKE_NONE) {
                        $listenersInvoker->invoke($class, Events::preUpload, $parent, new PreUploadEventArgs($parent, $this->om, $object, $form->getConfig()->getName(), $parseFile), $invoke);
                    }
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
