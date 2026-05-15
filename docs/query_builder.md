# QueryBuilder

We can use a QueryBuilder to query the models stored in Parse.

``` php
use App\ParseObject\Post;

$om = $this->get('doctrine_parse')->getManager();

$post =  $om->getRepository(Post::class)
    ->createQueryBuilder()
    ->field('title')->equals('Hello')
    ->getQuery()
    ->getSingleResult()
;
```

## Querying fields

You can query against a field with the following methods (inspired by Doctrine MongoDB) : 

- equals
- notEqual
- in (accept array)
- notIn (accept array)
- exists (accept boolean)
- gt (greater than)
- gte (greater than or equals)
- lt (lower than)
- lte (lower than or equals)
- contains (string contained in the attribute)
- regex (Regular expression with the delimiters `\Q` and `\E`)

## Querying associations

You can use the method `references` to query associations

``` php

$posts =  $om->getRepository(Post::class)
    ->createQueryBuilder()
    ->field('author')->references($user)
    ->getQuery()
    ->execute()
;
```

## Sorting and limiting results

With `limit` and `skip`, you can restrict the query results (as needed for a pagination).

The method `sort` takes an array as argument : 

``` php

$posts =  $om->getRepository(Post::class)
    ->createQueryBuilder()
    ->limit(10)
    ->skip(20)
    ->sort(['createdAt' => 'desc'])
    ->getQuery()
    ->execute()
;
```


## Include associated object

By default, the bundle lazy load the associated objects, which means that another query is executed when the association is requested.

It is however possible to force the fetching of a relation with the `includeKey` method : 

``` php

$posts_and_users =  $om->getRepository(Post::class)
    ->createQueryBuilder()
    ->includeKey('author')
    ->getQuery()
    ->execute()
;
```


## Select specific fields

You can limit which fields the server returns by using the `select` method. This is useful for reducing payload size when you only need a subset of the columns.

``` php

$posts =  $om->getRepository(Post::class)
    ->createQueryBuilder()
    ->select('title', 'createdAt')
    ->getQuery()
    ->execute()
;
```

Field names refer to your entity properties; they are translated to Parse column names automatically (this sets the `keys` query option on the Parse query).

When combined with `includeKey()`, the related pointer column must also be in the `select()` list, otherwise Parse will not return it.


## Disable hydration

By default, results are hydrated into entity instances managed by the `ObjectManager`. For read-only operations (exports, bulk reads, partial selections), you can skip hydration and receive raw `Parse\ParseObject` instances directly with `hydrate(false)`:

``` php

$rawObjects =  $om->getRepository(Post::class)
    ->createQueryBuilder()
    ->field('enabled')->equals(true)
    ->hydrate(false)
    ->getQuery()
    ->execute()
;

foreach ($rawObjects as $parseObject) {
    echo $parseObject->get('title');
}
```

When hydration is disabled, `execute()` returns a plain `array` (not an `ArrayCollection`) and `getSingleResult()` returns a `Parse\ParseObject` (or `null`). Raw `ParseObject` instances are **not** tracked by the `UnitOfWork`: modifications to them will not be persisted by `flush()`. Use this for read-only flows.


## Use a subquery

You can use a subquery against a field like this : 

``` php

$disabledUserQuery = $om->getRepository(Post::class)
    ->field('enabled')->equals(false)
    ->getQuery()
;

$posts =  $om->getRepository(Post::class)
    ->createQueryBuilder()
    ->field('author')->matchQuery($disabledUserQuery)
    ->getQuery()
    ->execute()
;
```


## Compound queries

You can use `OR` conditions :

```php
$qb = $om->getRepository(Post::class)->createQueryBuilder();
$qb->addOr($qb->expr()->field('firstname')->equals('Foo'));
$qb->addOr($qb->expr()->field('enabled')->equals(true));
```
