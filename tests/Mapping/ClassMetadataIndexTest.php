<?php

namespace Redking\ParseBundle\Tests\Mapping;

use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Mapping\MappingException;
use Redking\ParseBundle\Tests\Models\Blog\Post;

/**
 * Coverage for the index declarations held by ClassMetadata: order normalization,
 * option allowlist, and metadata cache serialization. No Parse Server needed.
 */
class ClassMetadataIndexTest extends TestCase
{
    private function metadata(): ClassMetadata
    {
        $cm = new ClassMetadata(Post::class);
        $cm->initializeReflection(new RuntimeReflectionService());

        return $cm;
    }

    public function testAClassHasNoIndexByDefault(): void
    {
        $cm = $this->metadata();

        $this->assertFalse($cm->hasIndexes());
        $this->assertSame([], $cm->getIndexes());
    }

    /**
     * @dataProvider provideOrders
     *
     * @param string|int $order
     * @param string|int $expected
     */
    public function testOrdersAreNormalized($order, $expected): void
    {
        $cm = $this->metadata();
        $cm->addIndex(['text' => $order]);

        $this->assertSame([['keys' => ['text' => $expected], 'options' => []]], $cm->getIndexes());
    }

    public static function provideOrders(): array
    {
        return [
            'asc' => ['asc', 1],
            'ASC' => ['ASC', 1],
            'desc' => ['desc', -1],
            'DESC' => ['DESC', -1],
            'one' => [1, 1],
            'minus one' => [-1, -1],
            'one as a string' => ['1', 1],
            'minus one as a string' => ['-1', -1],
            'text' => ['text', 'text'],
            '2d' => ['2d', '2d'],
            '2dsphere' => ['2dsphere', '2dsphere'],
            'hashed' => ['hashed', 'hashed'],
        ];
    }

    public function testTheListFormDefaultsToAscending(): void
    {
        $cm = $this->metadata();
        $cm->addIndex(['text', 'user']);

        $this->assertSame([['keys' => ['text' => 1, 'user' => 1], 'options' => []]], $cm->getIndexes());
    }

    public function testKeyOrderIsPreserved(): void
    {
        $cm = $this->metadata();
        $cm->addIndex(['user' => 'asc', 'text' => 'desc']);

        $this->assertSame(['user', 'text'], array_keys($cm->getIndexes()[0]['keys']));
    }

    public function testAnIndexWithoutKeyIsRejected(): void
    {
        $cm = $this->metadata();

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('has no key');
        $cm->addIndex([]);
    }

    public function testAnUnknownOrderIsRejected(): void
    {
        $cm = $this->metadata();

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Invalid index order "sideways"');
        $cm->addIndex(['text' => 'sideways']);
    }

    /**
     * Parse only carries the key specification of an index, so an option it can not
     * express has to fail loudly instead of being silently dropped.
     *
     * @dataProvider provideUnsupportedOptions
     */
    public function testAnUnsupportedOptionIsRejected(string $option): void
    {
        $cm = $this->metadata();

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf('Index option "%s"', $option));
        $cm->addIndex(['text' => 'asc'], [$option => true]);
    }

    public static function provideUnsupportedOptions(): array
    {
        return [['unique'], ['sparse'], ['background'], ['expireAfterSeconds'], ['partialFilterExpression']];
    }

    public function testTheNameOptionIsAccepted(): void
    {
        $cm = $this->metadata();
        $cm->addIndex(['text' => 'asc'], ['name' => 'my_index']);

        $this->assertSame(['name' => 'my_index'], $cm->getIndexes()[0]['options']);
    }

    /**
     * Without this the feature would be a no-op as soon as a metadata cache is in use.
     */
    public function testIndexesSurviveTheMetadataCache(): void
    {
        $cm = $this->metadata();
        $cm->addIndex(['text' => 'desc'], ['name' => 'my_index']);

        $restored = unserialize(serialize($cm));

        $this->assertSame($cm->getIndexes(), $restored->getIndexes());
    }

    public function testIndexesAreLeftOutOfTheSerializationWhenEmpty(): void
    {
        $this->assertNotContains('indexes', $this->metadata()->__sleep());
    }

    public function testIndexesArePartOfTheSerializationWhenDeclared(): void
    {
        $cm = $this->metadata();
        $cm->addIndex(['text' => 'asc']);

        $this->assertContains('indexes', $cm->__sleep());
    }
}
