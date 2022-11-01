# Events

This bundle dispatch the same type of Events as Doctrine so you can track the life-cycle of your models.

## Types

The type of events are defined in `Redking\ParseBundle\Events` : 

- preRemove
- postRemove
- prePersist
- postPersist
- preUpdate
- postUpdate
- preLoad
- postLoad
- loadClassMetadata
- onClassMetadataNotFound
- preFlush
- onFlush
- postFlush
- onClear
- preUpload

## Subscriber

So you can for exemple define an event subscriber like this : 

```yaml
# app/config/services.yml
services:
    # Doctrine event subscriber
    app.parse.event_subscriber:
        class: App\EventListener\ParseObjectEventSubscriber
        tags:
            - { name: doctrine_parse.event_subscriber }
```

and then : 

```php
<?php

namespace App\EventListener;

use App\ParseObject\Post;
use Doctrine\Common\EventSubscriber;
use Redking\ParseBundle\Event\LifecycleEventArgs;
use Redking\ParseBundle\Events;

class ParseObjectEventSubscriber implements EventSubscriber
{
    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            ];
    }

    /**
     * @param  LifecycleEventArgs $args [description]
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        $om = $args->getObjectManager();

        if ($object instanceof Post) {
            // ... do something
        }

    }
}
```
