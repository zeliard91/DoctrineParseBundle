<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Event;

use Doctrine\Persistence\Event\OnClearEventArgs as BaseOnClearEventArgs;
use Redking\ParseBundle\ObjectManager;

use function assert;

/**
 * Provides event arguments for the onClear event.
 */
final class OnClearEventArgs extends BaseOnClearEventArgs
{
    public function getObjectManager(): ObjectManager
    {
        $dm = $this->getObjectManager();
        assert($dm instanceof ObjectManager);

        return $dm;
    }

    public function getObjectClass(): ?string
    {
        return $this->getEntityClass();
    }

    /**
     * Returns whether this event clears all objects.
     */
    public function clearsAllObjects(): bool
    {
        return $this->clearsAllEntities();
    }
}