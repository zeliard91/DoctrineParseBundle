<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseQuery;
use Redking\ParseBundle\Event\LifecycleEventArgs;
use Redking\ParseBundle\Events;
use Redking\ParseBundle\Tests\Models\Blog\Picture;
use Redking\ParseBundle\Tests\Models\Blog\ProtectedIdModel;

/**
 * Reproduces the bug: "Cannot access protected property ClassName::$id"
 *
 * Root cause: ClassMetadata::wakeupReflection() and __wakeup() call
 * new \ReflectionProperty($mapping['declared'], $field) where $mapping['declared']
 * can be the name of a trait. A ReflectionProperty created from a trait name cannot
 * call setValue() on instances of a concrete class that uses that trait in PHP 8.x,
 * even with setAccessible(true).
 *
 * This manifests when:
 * 1. A class declares its $id via a trait (e.g. ObjectTrait)
 * 2. The ClassMetadata has 'declared' set to that trait name (from inheritance chain or cache corruption)
 * 3. UnitOfWork::executeDeletions() calls $class->reflFields[$class->identifier]->setValue($object, null)
 *
 * The test also covers the flush+clear loop scenario when a preRemove listener
 * schedules related objects for deletion.
 */
class ProtectedIdDeleteTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        ProtectedIdModel::class,
        Picture::class,
    ];

    /**
     * Tests that wakeupReflection() does not create a ReflectionProperty from a trait name.
     *
     * If 'declared' in fieldMappings points to a trait, ReflectionProperty::setValue()
     * throws "Cannot access protected property ClassName::$id" in PHP 8.x.
     */
    public function testWakeupReflectionWithTraitDeclaredDoesNotThrow(): void
    {
        $meta = $this->om->getClassMetadata(ProtectedIdModel::class);

        // Inject 'declared' = ObjectTrait name (the trait that declares $id in most Parse models)
        // This simulates what can happen with cached/corrupted metadata where
        // 'declared' points to a trait instead of the concrete class.
        $meta->fieldMappings['id']['declared'] = \Redking\ParseBundle\ObjectTrait::class;

        // wakeupReflection() must not create a ReflectionProperty from the trait
        // which would make setValue() fail with "Cannot access protected property"
        $meta->wakeupReflection();

        $obj = new ProtectedIdModel();
        $this->assertNull($obj->getId());

        // This is the exact call from UnitOfWork::executeDeletions() line 1193
        // It must not throw "Cannot access protected property ProtectedIdModel::$id"
        $meta->reflFields[$meta->identifier]->setValue($obj, null);

        $this->assertNull($obj->getId());
    }

    /**
     * Same test for __wakeup() path (used when ClassMetadata is deserialized from Redis cache).
     */
    public function testWakeupMagicWithTraitDeclaredDoesNotThrow(): void
    {
        $meta = $this->om->getClassMetadata(ProtectedIdModel::class);

        // Inject 'declared' = ObjectTrait name before serialization
        $meta->fieldMappings['id']['declared'] = \Redking\ParseBundle\ObjectTrait::class;

        // Simulate Redis cache: serialize then unserialize (triggers __wakeup)
        $deserialized = unserialize(serialize($meta));

        $obj = new ProtectedIdModel();

        // This is the exact call from UnitOfWork::executeDeletions() line 1193
        $deserialized->reflFields[$deserialized->identifier]->setValue($obj, null);

        $this->assertNull($obj->getId());
    }

    /**
     * Registers a preRemove listener that removes screenshots before deleting
     * the parent object.
     */
    private function registerPreRemoveScreenshotListener(): void
    {
        $om = $this->om;
        $this->om->getEventManager()->addEventListener(
            [Events::preRemove],
            new class($om) {
                public function __construct(private $om) {}

                public function preRemove(LifecycleEventArgs $args): void
                {
                    $object = $args->getObject();
                    if ($object instanceof ProtectedIdModel) {
                        foreach ($object->getScreenshots() as $screenshot) {
                            $this->om->remove($screenshot);
                        }
                    }
                }
            }
        );
    }

    /**
     * Tests that deleting objects with protected $id in a flush+clear loop does not
     * throw "Cannot access protected property" when a preRemove listener schedules
     * non-cascaded related objects for deletion.
     */
    public function testDeleteWithProtectedIdAndPreRemoveListener(): void
    {
        $this->registerPreRemoveScreenshotListener();

        // Create several ProtectedIdModel objects, each with 2 screenshots
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $model = new ProtectedIdModel();
            $model->setName('model_' . $i);

            $screenshot1 = new Picture();
            $screenshot1->setFile('screenshot_' . $i . '_1.jpg');
            $model->addScreenshot($screenshot1);

            $screenshot2 = new Picture();
            $screenshot2->setFile('screenshot_' . $i . '_2.jpg');
            $model->addScreenshot($screenshot2);

            $this->om->persist($model);
            $this->om->persist($screenshot1);
            $this->om->persist($screenshot2);
        }
        $this->om->flush();
        $this->om->clear();

        // Fetch all IDs
        $allModels = $this->om->getRepository(ProtectedIdModel::class)->findAll();
        foreach ($allModels as $model) {
            $ids[] = $model->getId();
        }
        $this->om->clear();

        $this->assertCount(5, $ids);

        // Delete in batches of 2 with flush+clear per batch
        $repo = $this->om->getRepository(ProtectedIdModel::class);
        $nbRecords = count($ids);
        $cpt = 0;

        foreach (array_chunk($ids, 2) as $chunk) {
            $cpt += count($chunk);
            $objects = $repo->createQueryBuilder()
                ->field('id')->in($chunk)
                ->getQuery()
                ->execute();

            $lastObject = null;
            foreach ($objects as $object) {
                $lastObject = $object;
                $this->om->remove($object);
            }

            $this->assertNotNull($lastObject->getId(), 'Object should have an id before flush');
            $this->om->flush();
            $this->om->clear();

            // After flush, $id should be null (set by executeDeletions)
            $this->assertNull($lastObject->getId(), 'Object id should be null after deletion');
        }

        $this->assertEquals($nbRecords, $cpt);

        // Verify all objects are deleted
        $remaining = $this->om->getRepository(ProtectedIdModel::class)->findAll();
        $this->assertEmpty($remaining, 'All ProtectedIdModel objects should be deleted');

        // Verify screenshots are also deleted (by the preRemove listener)
        $remainingPictures = $this->om->getRepository(Picture::class)->findAll();
        $this->assertEmpty($remainingPictures, 'All screenshots should be deleted by preRemove listener');
    }
}
