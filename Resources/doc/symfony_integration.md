# Symfony Integration

Here are some helpers for your symfony application.

## ParamConverter

You can directly use Parse objects in controllers with the built in parameter converter


```php
<?php

namespace App\Controller;


use App\ParseObject\Post;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class PostController extends AbstractController
{
    /**
     * Edit a Post.
     *
     * @Route(path="/post/{post}/edit", name="app_post_edit")
     */
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