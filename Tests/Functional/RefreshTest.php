<?php

namespace Redking\ParseBundle\Tests\Functional;

use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\Models\Blog\Post;

class RefreshTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\User',
        'Redking\ParseBundle\Tests\Models\Blog\Post',
    ];

    public function testRefresh()
    {
        $user = (new User())
            ->setName('Foo')
            ->setPassword('bar')
        ;

        $post = (new Post())
            ->setUser($user)
            ->setText('Lorem ipsum')
        ;

        $this->om->persist($user);
        $this->om->persist($post);
        $this->om->flush();

        $postId = $post->getId();
        $this->om->clear();

        /**
         * @var Post|null $post
         */
        $post = $this->om->find(Post::class, $postId);
        $this->assertNotNull($post, 'Test inserted post is reloaded');

        $post->setText('new text');
        $post->getUser()->setName('Foo edited');

        $this->om->refresh($post);
        $this->om->refresh($post->getUser());
        $this->om->flush();
        $this->om->clear();

        /**
         * @var Post|null $post
         */
        $post = $this->om->find(Post::class, $postId);
        $this->assertNotNull($post, 'Test inserted post is reloaded');

        $this->assertEquals('Lorem ipsum', $post->getText(), 'Check that post text has not been changed');
        $this->assertEquals('Foo', $post->getUser()->getName(), 'Check that user name has not been changed');
    }

    public function testRefreshFromNull()
    {
        $user = (new User())
            ->setName('Bar')
            ->setPassword('yolo')
        ;

        $post = (new Post())
            ->setUser($user)
        ;

        $this->om->persist($user);
        $this->om->persist($post);
        $this->om->flush();

        $postId = $post->getId();
        $this->om->clear();

        /**
         * @var Post|null $post
         */
        $post = $this->om->find(Post::class, $postId);
        $this->assertNotNull($post, 'Test inserted post is reloaded');
        $this->assertNull($post->getText(), 'Check that post text is set to null');

        $post->setText('temporary edition');
        $this->om->refresh($post);
        $this->om->flush();
        $this->om->clear();

        /**
         * @var Post|null $post
         */
        $post = $this->om->find(Post::class, $postId);
        $this->assertNotNull($post, 'Test inserted post is reloaded');
        $this->assertNull($post->getText(), 'Check that post text has not been changed from null value');
    }
}