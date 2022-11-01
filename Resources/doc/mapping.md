# Mapping

You need to define the mapping for the models stored in Parse.
The files need to be stored in the `ParseObject` directory of your bundle(s).

The mapping can be defined with annotations or in yaml.

## Drivers


```php
<?php

namespace Acme\FooBundle\ParseObject;

use Acme\FooBundle\ParseObject\User;
use Redking\ParseBundle\Mapping\Annotations as ORM;
use Redking\ParseBundle\ObjectTrait;

/**
 * @ORM\ParseObject(collection="Post")
 */
class Post
{
    // Define $id, $createdAt and $updatedAt
    use ObjectTrait;

    /**
     * @var string
     * @ORM\Field(type="string", name="Title")
     */
    protected $title;

    /**
     * @var Acme\FooBundle\ParseObject\User
     * @ORM\ReferenceOne(targetDocument="Acme\FooBundle\ParseObject\User")
     */
    protected $author;

    public function getId(): string
    {
        return $this->id;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setAuthor(User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }
}
```

or in YAML format : 

```yaml
# Acme/FooBundle/Resources/config/doctrine/Post.parse.yml
Acme\FooBundle\ParseObject\Post:
    # name of the collection in Parse store
    collection: Post
    fields:
        title:
            # if a different name is used in Parse store
            name: Title
            type: string
    referenceOne:
        author:
            targetDocument: User

```

You can use a specific command to generate getters and setters when the mapping is defined : 

`php app/console doctrine:parse:generate:objects AcmeFooBundle`


## Repository

It's possible to have a specific Repository class for a model instead of the one by default.

For that, you have to specify it in the mapping: 

```php
<?php
/**
 * @ORM\ParseObject(collection="Post", repositoryClass="Acme\FooBundle\ParseObject\Repository\PostRepository")
 */
class Post
{
    ...
}
```

or in YAML : 

```yaml
# Acme/FooBundle/Resources/config/doctrine/Post.parse.yml
Acme\FooBundle\ParseObject\Post:
    collection: Post
    repositoryClass: Acme\FooBundle\ParseObject\Repository\PostRepository
```

Then, you define the repository class : 

```php
<?php

namespace Acme\FooBundle\ParseObject\Repository;

use Redking\ParseBundle\ObjectRepository;

class PostRepository extends ObjectRepository
{
    // ...
}
```


## Attribute Types

The `field` type can be one the following : 

- string
- integer
- float
- boolean
- date
- array (simple collection)
- hash (key-value array)
- geopoint ([Parse\ParseGeoPoint](https://github.com/ParsePlatform/parse-php-sdk/blob/master/src/Parse/ParseGeoPoint.php))
- file (store the content of a file in Parse)


## Relations between objects

You can define One-To-One relation with `referenceOne` and One-To-Many (or Many-To-Many) with `referenceMany`

As with Doctrine ORM, you can have bi-directionnal relations by defining the option `inversedBy` on the owning side and `mappedBy` on the other side.

Cascading is also supported with the option `cascade=(all|remove|persist|merge|refresh|detach)`

If you define the option `orphanRemoval` with `true`, the orphans of a relation can be removed.
