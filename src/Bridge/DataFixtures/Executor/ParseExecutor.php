<?php

namespace Redking\ParseBundle\Bridge\DataFixtures\Executor;

use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Redking\ParseBundle\Bridge\DataFixtures\Event\Listener\ParseReferenceListener;
use Redking\ParseBundle\Bridge\DataFixtures\Purger\ParsePurger;
use Redking\ParseBundle\ObjectManager;

/**
 * Class responsible for executing data fixtures.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class ParseExecutor extends AbstractExecutor
{
    /**
     * Construct new fixtures loader instance.
     *
     * @param ObjectManager $om ObjectManager instance used for persistence.
     */
    public function __construct(ObjectManager $om, ParsePurger $purger = null)
    {
        $this->om = $om;
        if ($purger !== null) {
            $this->purger = $purger;
            $this->purger->setObjectManager($om);
        }
        parent::__construct($om);
        $this->listener = new ParseReferenceListener($this->referenceRepository);
        $om->getEventManager()->addEventSubscriber($this->listener);
    }

    /**
     * Retrieve the ObjectManager instance this executor instance is using.
     *
     * @return \Redking\ParseBundle\ObjectManager
     */
    public function getObjectManager()
    {
        return $this->om;
    }

    /** @inheritDoc */
    public function setReferenceRepository(ReferenceRepository $referenceRepository)
    {
        $this->om->getEventManager()->removeEventListener(
            $this->listener->getSubscribedEvents(),
            $this->listener
        );

        $this->referenceRepository = $referenceRepository;
        $this->listener = new ParseReferenceListener($this->referenceRepository);
        $this->om->getEventManager()->addEventSubscriber($this->listener);
    }

    /** @inheritDoc */
    public function execute(array $fixtures, $append = false)
    {
        if ($append === false) {
            $this->purge();
        }
        foreach ($fixtures as $fixture) {
            $this->load($this->om, $fixture);
        }
    }
}
