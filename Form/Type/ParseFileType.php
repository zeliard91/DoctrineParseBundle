<?php

namespace Redking\ParseBundle\Form\Type;

use Parse\ParseFile;
use Redking\ParseBundle\Event\ListenersInvoker;
use Redking\ParseBundle\Event\PreUploadEventArgs;
use Redking\ParseBundle\Exception\WrappedParseException;
use Redking\ParseBundle\Events;
use Redking\ParseBundle\Form\DataTransformer\ParseFileTransformer;
use Redking\ParseBundle\ObjectManager;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

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

        $builder->addModelTransformer(new ParseFileTransformer($options));
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function(FormEvent $event){
            $object = $event->getData();
            $form = $event->getForm();
            $parseFile = $form->getData();

            // reset model data if there is no upload
            if (null === $object && $parseFile instanceof ParseFile) {
                $event->setData($parseFile);
            }
        });
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($options) {
                $object = $event->getData();
                $form = $event->getForm();
                $parseFile = $form->getData();

                if ($object instanceof UploadedFile && $parseFile instanceof ParseFile) {
                    // Search for Object parent class
                    $parent = $form->getParent();
                    while (null === $parent->getConfig()->getDataClass() && null !== $parent->getParent()) {
                        $parent = $parent->getParent();
                    }

                    if (null === $parent->getConfig()->getDataClass()) {
                        throw new \Exception('Unable to find parent object class for this ParseFileType');
                    }

                    // Dispatch preUpload Event on parent ParseObject
                    $class = $this->om->getClassMetadata($parent->getConfig()->getDataClass());
                    $parent = $parent->getData();

                    $listenersInvoker = $this->om->getUnitOfWork()->getListenersInvoker();
                    $invoke = $listenersInvoker->getSubscribedSystems($class, Events::preUpload);

                    if ($invoke !== ListenersInvoker::INVOKE_NONE) {
                        $listenersInvoker->invoke($class, Events::preUpload, $parent, new PreUploadEventArgs($parent, $this->om, $object, $form->getConfig()->getName(), $parseFile), $invoke);
                    }
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
            'autocorrect_name' => true,
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
    public function getBlockPrefix(): string
    {
        return 'parse_file';
    }
}
