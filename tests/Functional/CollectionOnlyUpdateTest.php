<?php

namespace Redking\ParseBundle\Tests\Functional;

use Doctrine\Common\Collections\ArrayCollection;
use Parse\ParseObject;
use Parse\ParseQuery;
use Redking\ParseBundle\Tests\Models\Blog\Address;
use Redking\ParseBundle\Tests\Models\Blog\Article;
use Redking\ParseBundle\Tests\Models\Blog\Tag;
use Redking\ParseBundle\Tests\Models\Blog\User;

/**
 * A flush where only a collection of an object changed must write that collection alone.
 *
 * The other fields of the object hold the state it was loaded with: writing them back
 * reverts whatever another process saved on the object in the meantime.
 */
class CollectionOnlyUpdateTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        Article::class,
        Tag::class,
        User::class,
        Address::class,
    ];

    public function testAddingToAnArrayCollectionKeepsFieldsSavedMeanwhile(): void
    {
        $articleId = $this->createArticle('Loaded title', ['first']);

        $article = $this->om->getRepository(Article::class)->find($articleId);
        $this->saveOutOfBand('blog_article', $articleId, 'title', 'Saved meanwhile');

        $article->addTag($this->createTag('second'));
        $this->om->flush();

        $stored = $this->fetchRaw('blog_article', $articleId);
        self::assertSame('Saved meanwhile', $stored->get('title'));
        self::assertCount(2, $stored->get('tags'));
    }

    public function testRemovingFromAnArrayCollectionKeepsFieldsSavedMeanwhile(): void
    {
        $articleId = $this->createArticle('Loaded title', ['first', 'second']);

        $article = $this->om->getRepository(Article::class)->find($articleId);
        $this->saveOutOfBand('blog_article', $articleId, 'title', 'Saved meanwhile');

        $article->removeTag($article->getTags()->first());
        $this->om->flush();

        $stored = $this->fetchRaw('blog_article', $articleId);
        self::assertSame('Saved meanwhile', $stored->get('title'));
        self::assertCount(1, $stored->get('tags'));
    }

    public function testEmptyingAnArrayCollectionKeepsFieldsSavedMeanwhile(): void
    {
        $articleId = $this->createArticle('Loaded title', ['first']);

        $article = $this->om->getRepository(Article::class)->find($articleId);
        $this->saveOutOfBand('blog_article', $articleId, 'title', 'Saved meanwhile');

        $article->removeTag($article->getTags()->first());
        $this->om->flush();

        $stored = $this->fetchRaw('blog_article', $articleId);
        self::assertSame('Saved meanwhile', $stored->get('title'));
        self::assertEmpty($stored->get('tags'));
    }

    public function testReplacingAnArrayCollectionKeepsFieldsSavedMeanwhile(): void
    {
        $articleId = $this->createArticle('Loaded title', ['first']);

        $article = $this->om->getRepository(Article::class)->find($articleId);
        $this->saveOutOfBand('blog_article', $articleId, 'title', 'Saved meanwhile');

        // A collection the unit of work does not own yet, as a setter with an array would do
        $tags = new \ReflectionProperty(Article::class, 'tags');
        $tags->setValue($article, new ArrayCollection([$this->createTag('second'), $this->createTag('third')]));
        $this->om->flush();

        $stored = $this->fetchRaw('blog_article', $articleId);
        self::assertSame('Saved meanwhile', $stored->get('title'));
        self::assertSame(
            ['second', 'third'],
            array_map(fn (ParseObject $tag) => $this->fetchRaw('blog_tag', $tag->getObjectId())->get('name'), $stored->get('tags'))
        );
    }

    public function testAddingToARelationKeepsFieldsSavedMeanwhile(): void
    {
        $user = new User();
        $user->setName('loaded-name');
        $user->setPassword('secret');
        $user->addAddress((new Address())->setCity('Paris'));
        $this->om->persist($user);
        $this->om->flush();
        $userId = $user->getId();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->find($userId);
        $this->saveOutOfBand('_User', $userId, 'username', 'saved-meanwhile');

        $user->addAddress((new Address())->setCity('Lyon'));
        $this->om->flush();

        $stored = $this->fetchRaw('_User', $userId);
        self::assertSame('saved-meanwhile', $stored->get('username'));
        self::assertCount(2, $stored->getRelation('addresses')->getQuery()->find(true));
    }

    public function testFieldAndCollectionChangedTogetherAreBothWritten(): void
    {
        $articleId = $this->createArticle('Loaded title', ['first']);

        $article = $this->om->getRepository(Article::class)->find($articleId);
        $article->setTitle('Changed title');
        $article->addTag($this->createTag('second'));
        $this->om->flush();

        $stored = $this->fetchRaw('blog_article', $articleId);
        self::assertSame('Changed title', $stored->get('title'));
        self::assertCount(2, $stored->get('tags'));
    }

    public function testFieldChangedAfterACollectionOnlyFlushIsWritten(): void
    {
        $articleId = $this->createArticle('Loaded title', ['first']);

        $article = $this->om->getRepository(Article::class)->find($articleId);
        $article->addTag($this->createTag('second'));
        $this->om->flush();

        self::assertSame('Loaded title', $this->om->getUnitOfWork()->getOriginalObjectData($article)->get('title'));
        self::assertCount(2, $this->om->getUnitOfWork()->getOriginalObjectData($article)->get('tags'));

        $article->setTitle('Changed title');
        $this->om->flush();

        // Nothing is left to write
        $this->om->getUnitOfWork()->computeChangeSets();
        self::assertFalse($this->om->getUnitOfWork()->isScheduledForUpdate($article));

        $this->om->clear();
        $article = $this->om->getRepository(Article::class)->find($articleId);
        self::assertSame('Changed title', $article->getTitle());
        self::assertCount(2, $article->getTags());
    }

    private function createArticle(string $title, array $tagNames): string
    {
        $article = (new Article())->setTitle($title);
        foreach ($tagNames as $name) {
            $article->addTag($this->createTag($name));
        }
        $this->om->persist($article);
        $this->om->flush();
        $this->om->clear();

        return $article->getId();
    }

    private function createTag(string $name): Tag
    {
        $tag = (new Tag())->setName($name);
        $this->om->persist($tag);
        $this->om->flush($tag);

        return $tag;
    }

    private function saveOutOfBand(string $className, string $objectId, string $field, string $value): void
    {
        $object = ParseObject::create($className, $objectId, true);
        $object->set($field, $value);
        $object->save(true);
        self::assertNotNull($object->getUpdatedAt(), 'The out-of-band write must be accepted by the server');
    }

    private function fetchRaw(string $className, string $objectId): ParseObject
    {
        return (new ParseQuery($className))->get($objectId, true);
    }
}
