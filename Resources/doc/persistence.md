# Persistence

The life-cycle of the persisted objects is handled the same way as in Doctrine ORM/MongoDB.


## Create


``` php
use Acme\FooBundle\ParseObject\Post;

$om = $this->get('doctrine_parse')->getManager();

$post = new Post();
$post->setTitle('Hello');

$om->persist($post);
$om->flush();

```


## Read

``` php
$post = $om->getRepository(Post::class)->find('UgdfttF');

$posts = $om->getRepository(Post::class)->findAll();

```


## Update

``` php
$post->setTitle('Hello Worl!');

$om->persist($post);
$om->flush();

```


## Delete

``` php
$om->remove($post);
$om->flush();

```