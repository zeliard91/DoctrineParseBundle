# Mapping

You need to define the mapping for the models stored in Parse.
The files need to be stored in the `ParseObject` directory of your app.

The mapping can be defined with attributes or in yaml.

## Drivers


```php
<?php
// src/ParseObject/Post.pĥp

namespace App\ParseObject;

use App\ParseObject\User;
use Redking\ParseBundle\Mapping\Annotations as ORM;
use Redking\ParseBundle\ObjectTrait;

#[ORM\ParseObject(collection:'Post')]
class Post
{
    // Define $id, $createdAt and $updatedAt
    use ObjectTrait;

    /**
     * @var string
     */
    #[ORM\Field(type: 'string', name: 'Title')]
    protected $title;

    /**
     * @var App\ParseObject\User
     */
    #[ORM\ReferenceOne(targetDocument: \App\ParseObject\User::class)]
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
# /config/doctrine/Post.parse.yml
App\ParseObject\Post:
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

`php bin/console doctrine:parse:generate:objects AcmeFooBundle`


## Repository

It's possible to have a specific Repository class for a model instead of the one by default.

For that, you have to specify it in the mapping: 

```php
<?php
#[ORM\ParseObject(collection: 'Post', repositoryClass: \App\Repository\PostRepository::class)]
class Post
{
    ...
}
```

or in YAML : 

```yaml
# /config/doctrine/Post.parse.yml
App\ParseObject\Post:
    collection: Post
    repositoryClass: App\Repository\PostRepository
```

Then, you define the repository class : 

```php
<?php

namespace App\Repository;

use Redking\ParseBundle\ObjectRepository;

class PostRepository extends ObjectRepository
{
    // ...
}
```


## Attribute Types

The `field` type can be one of the built-in types provided by the bundle.

For a complete list of all available types with detailed examples and usage information, see the [**Field Types documentation**](types.md).

**Quick reference:**
- `string` - Text values
- `encrypted_string` - Automatically encrypted strings (emails, SSN, etc.)
- `integer` / `float` / `boolean` - Numeric and boolean values
- `date` - DateTime objects
- `array` / `hash` - Collections and key-value data
- `geopoint` - Geographic coordinates
- `file` - File content storage


## Relations between objects

You can define One-To-One relation with `referenceOne` and One-To-Many (or Many-To-Many) with `referenceMany`

As with Doctrine ORM, you can have bi-directionnal relations by defining the option `inversedBy` on the owning side and `mappedBy` on the other side.

Cascading is also supported with the option `cascade=(all|remove|persist|merge|refresh|detach)`

> **Note on `cascade: persist` on the inverse side (`mappedBy`):** only new objects (without an identifier) added to the collection will be automatically persisted on `flush()`. Changes to existing objects are not propagated from the inverse side — the owning side (`inversedBy`) is authoritative for updates.

If you define the option `orphanRemoval` with `true`, the orphans of a relation can be removed.


## Indexes

Indexes can be declared in the mapping, then propagated to Parse with the
[`doctrine:parse:schema:*` commands](symfony_integration.md#schema-commands).

An index is declared either on the class, for a compound index, or on a single property:

```php
<?php
// src/ParseObject/Post.php

namespace App\ParseObject;

use Redking\ParseBundle\Mapping\Annotations as ORM;
use Redking\ParseBundle\ObjectTrait;

#[ORM\ParseObject(collection: 'Post')]
#[ORM\Index(keys: ['published' => 'asc', 'author' => 'desc'])]
#[ORM\Index(keys: ['title' => 'asc'], name: 'title_lookup')]
class Post
{
    use ObjectTrait;

    #[ORM\Field(type: 'string', name: 'Title')]
    #[ORM\Index]
    protected $title;

    #[ORM\Field(type: 'integer', name: 'hits')]
    #[ORM\Index(order: 'desc')]
    protected $viewCount;

    #[ORM\Field(type: 'boolean')]
    protected $published;

    #[ORM\ReferenceOne(targetDocument: \App\ParseObject\User::class)]
    protected $author;
}
```

`#[ORM\Index]` is repeatable, on a class as well as on a property.

or in YAML format:

```yaml
# /config/doctrine/Post.parse.yml
App\ParseObject\Post:
    collection: Post
    indexes:
        -
            keys:
                published: asc
                author: desc
        -
            keys:
                title: asc
            options:
                name: title_lookup
    fields:
        title:
            name: Title
            type: string
            index: true
        viewCount:
            name: hits
            type: integer
            index:
                order: desc
    referenceOne:
        author:
            targetDocument: User
```

The keys are declared with the **PHP field names**; the bundle resolves them to the
column names MongoDB actually uses.

The accepted orders are `asc` / `1`, `desc` / `-1`, and `text`, `2d`, `2dsphere`,
`hashed` for the corresponding MongoDB index types. A `geopoint` field can only be
indexed with `2dsphere`.

When the `name` option is omitted, the name MongoDB would have given to the index is
generated: the resolved column names and their orders, joined by underscores
(`published_1__p_author_-1`). This makes an index created by hand collide by identity
with the mapped one, instead of being duplicated under another name.

### What Parse can not express

The Parse schema API carries nothing but the MongoDB key specification of an index, so
the following options are **not** supported and are rejected with a `MappingException`
rather than silently ignored:

- `unique`
- `sparse`
- `expireAfterSeconds` (TTL)
- `partialFilterExpression`

Such an index has to be created directly in MongoDB. Parse Server reads the real MongoDB
indexes back into its schema metadata when it starts, so the bundle will then see it as
an existing index and leave it alone.

The following fields can not be indexed either:

| Field | Reason |
| --- | --- |
| the identifier | stored as the MongoDB `_id` column, which is always indexed |
| `createdAt` / `updatedAt` | stored as `_created_at` / `_updated_at`, which the Parse schema API rejects because they are not schema fields. Create the index directly in MongoDB. |
| the inverse side of a reference (`mappedBy`) | it has no column in the Parse class |
| a `referenceMany` with `implementation: relation` | the data lives in a separate `_Join` collection |

A `referenceOne` is stored as a Parse Pointer, whose MongoDB column is prefixed with
`_p_`: an index on the `author` property above ends up as `{"_p_author": 1}`. A
`referenceMany` with the default `array` implementation keeps its column name.

MongoDB accepts a single `text` index per collection, so at most one field of a class
may be declared with `order: text`.
