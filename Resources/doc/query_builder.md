# QueryBuilder

We can use a QueryBuilder to query the models stored in Parse.

``` php

$om = $this->get('doctrine_parse')->getManager();

$post =  $om->getRepository('AcmeFooBundle:Post')
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

$posts =  $om->getRepository('AcmeFooBundle:Post')
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

$posts =  $om->getRepository('AcmeFooBundle:Post')
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

$posts_and_users =  $om->getRepository('AcmeFooBundle:Post')
    ->createQueryBuilder()
    ->includeKey('author')
    ->getQuery()
    ->execute()
;
```


## Use a subquery

You can use a subquery against a field like this : 

``` php

$disabledUserQuery = $om->getRepository('AcmeFooBundle:User')
    ->field('enabled')->equals(false)
    ->getQuery()
;

$posts =  $om->getRepository('AcmeFooBundle:Post')
    ->createQueryBuilder()
    ->field('author')->matchQuery($disabledUserQuery)
    ->getQuery()
    ->execute()
;
```


## Compound queries

You can use `OR` conditions :

```php
$qb = $om->getRepository('AcmeFooBundle:User')->createQueryBuilder();
$qb->addOr($qb->expr()->field('firstname')->equals('Foo'));
$qb->addOr($qb->expr()->field('enabled')->equals(true));
```
