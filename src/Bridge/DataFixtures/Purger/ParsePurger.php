<?php


namespace Redking\ParseBundle\Bridge\DataFixtures\Purger;

use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Redking\ParseBundle\ObjectManager;

/**
 * Class responsible for purging databases of data before reloading data fixtures.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class ParsePurger implements PurgerInterface
{
    /** ObjectManager instance used for persistence. */
    private $om;

    /**
     * Construct new purger instance.
     *
     * @param ObjectManager $om ObjectManager instance used for persistence.
     */
    public function __construct(ObjectManager $om = null)
    {
        $this->om = $om;
    }

    /**
     * Set the ObjectManager instance this purger instance should use.
     *
     * @param ObjectManager $om
     */
    public function setObjectManager(ObjectManager $om)
    {
        $this->om = $om;
    }

    /**
     * Retrieve the ObjectManager instance this purger instance is using.
     *
     * @return \Redking\ParseBundle\ObjectManager
     */
    public function getObjectManager()
    {
        return $this->om;
    }

    public function purge(): void
    {
        $this->om->getSchemaManager()->dropCollections();
    }
}
