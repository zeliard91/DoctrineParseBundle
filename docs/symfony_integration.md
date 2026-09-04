# Symfony Integration

Here are some helpers for your symfony application.

## ParamConverter

You can directly use Parse objects in controllers with the built in parameter converter


```php
<?php

namespace App\Controller;


use App\ParseObject\Post;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

class PostController extends AbstractController
{
    /**
     * Edit a Post.
     */
    #[Route('/post/{post}/edit', name: 'app_post_edit')]
    public function edit(Post $post, Request $request)
    {
        // build and handle form with $post
    }
}

```

Can be called in twig templates

```twig
<a href="{{ path('app_post_edit', {'post': post.id}) }}">Edit</a>
```

## Schema commands

Three commands propagate the [indexes declared in the mapping](mapping.md#indexes) to
Parse. They all accept `--class|-c` to restrict the run to some object classes (repeatable,
FQCN), `--index|-i`, and `--dry-run` to print the planned operations without writing
anything.

Parse requires a **master key** for every schema operation, so `redking_parse.master_key`
has to be configured.

| Command | Effect |
| --- | --- |
| `doctrine:parse:schema:create` | Creates the classes, the missing fields and the missing indexes. Purely additive. |
| `doctrine:parse:schema:update` | Same, and recreates the mapped indexes whose keys changed. |
| `doctrine:parse:schema:drop` | Drops the indexes declared in the mapping. Never deletes a class, a field or any data. |

```bash
# See what would change, without touching Parse
php bin/console doctrine:parse:schema:update --dry-run

# Synchronize a single class
php bin/console doctrine:parse:schema:update -c 'App\ParseObject\Post'
```

The output lists the **resolved Parse column names** (`_p_author`…), which is what makes
it comparable with the indexes Parse actually holds. Use `-v` to also list the unchanged
indexes and the ones that are not managed by the bundle.

### Contract

- **Additive on fields.** A field missing from the Parse class is created; an existing
  field is never modified nor deleted, because Parse can not change the type of a field.
  Pass `--no-fields` to `create`/`update` to skip field creation entirely — but be aware
  that Parse refuses an index on a column it does not know about.
- **An index absent from the mapping is never dropped.** `_id_`, the indexes Parse
  maintains on `_User` and `_Role`, and any index created by hand are only reported (with
  `-v`), never touched.
- **`create` never destroys anything.** An index whose keys differ from the mapping is
  reported as a mismatch, so that `update` can recreate it.
- **`update` is not atomic.** Recreating an index takes two requests, a drop then a
  create, because Parse refuses to delete and add the same index name at once. If the
  second request fails, the index stays dropped and the command has to be run again.
- **Parse schema metadata can be stale.** Parse Server reads the real MongoDB indexes back
  into its schema metadata only when it starts. After an out-of-band change in MongoDB,
  restart Parse Server before running these commands.
