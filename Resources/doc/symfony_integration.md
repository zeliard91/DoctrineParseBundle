# Symfony Integration

Here are some helpers for your symfony application.

## ParamConverter

You can use a Doctrine ParamConverter in your controllers for your Parse models with the right converter : 


```php
<?php

namespace Acme\FooBundle\Controller;


use Acme\FooBundle\ParseObject\Post;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class PostController extends Controller 
{
    /**
     * Edit a Post.
     *
     * @ParamConverter("post", class="AcmeFooBundle:Post", converter="doctrine.parse")
     * @param  \Acme\FooBundle\ParseObject\Post $post
     */
    public function editAction(Post $post)
    {
        // 
    }
}

```