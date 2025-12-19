# Encrypted Routing

DoctrineParseBundle provides a secure way to expose Parse object IDs in public URLs by encrypting them. This prevents exposing internal IDs while maintaining routing functionality.

## Why Encrypt IDs?

Exposing raw Parse object IDs in URLs can present security concerns:
- **Enumeration attacks**: Users can guess IDs and access resources they shouldn't
- **Information leakage**: Sequential IDs reveal information about your data
- **Direct object reference**: Makes it easier to tamper with URLs

Encrypted routing solves these issues by:
- Making IDs unpredictable and non-sequential
- Preventing enumeration attacks
- Adding authentication via HMAC to detect tampering

## Configuration

The encrypted routing system uses the same encryption service as encrypted fields. By default, it uses `kernel.secret`, but you can configure custom keys:

```env
# .env
APP_ENCRYPTION_KEY=your_32_bytes_encryption_key_here
APP_HMAC_KEY=your_32_bytes_hmac_key_here
```

**Important**: For encrypted routing, the system automatically uses `base64url` encoding (URL-safe) instead of standard `base64`.

## Basic Usage

### 1. Mark Controller Parameters with `#[EncryptedId]`

Use the `#[EncryptedId]` attribute on controller parameters to automatically decrypt and load Parse objects:

```php
<?php

namespace App\Controller;

use App\ParseObject\Post;
use Redking\ParseBundle\Attribute\EncryptedId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PostController extends AbstractController
{
    #[Route('/post/{post}', name: 'post_show')]
    public function show(
        #[EncryptedId(Post::class)] Post $post
    ): Response {
        // $post is automatically decrypted and loaded
        return $this->render('post/show.html.twig', [
            'post' => $post,
        ]);
    }
}
```

### 2. Generate Encrypted URLs

#### Using the Service (in Controllers)

```php
use Redking\ParseBundle\Routing\EncryptedUrlGenerator;

class PostController extends AbstractController
{
    public function __construct(
        private readonly EncryptedUrlGenerator $urlGenerator
    ) {}

    public function list(): Response
    {
        $posts = $this->om->getRepository(Post::class)->findAll();

        $urls = [];
        foreach ($posts as $post) {
            // Generate encrypted URL
            $urls[$post->getId()] = $this->urlGenerator->generate(
                'post_show',
                ['post' => $post->getId()],
                'post'  // parameter name to encrypt
            );
        }

        return $this->render('post/list.html.twig', [
            'posts' => $posts,
            'urls' => $urls,
        ]);
    }
}
```

#### Using Twig Functions (in Templates)

```twig
{# Generate a relative path with encrypted ID #}
<a href="{{ encrypted_path('post_show', {'post': post.id}, 'post') }}">
    View Post
</a>

{# Generate an absolute URL with encrypted ID #}
<a href="{{ encrypted_url('post_show', {'post': post.id}, 'post') }}">
    View Post (Absolute)
</a>
```

## Advanced Usage

### Multiple Encrypted Parameters

You can encrypt multiple parameters in a single route:

```php
#[Route('/user/{user}/post/{post}', name: 'user_post_show')]
public function showUserPost(
    #[EncryptedId(User::class)] User $user,
    #[EncryptedId(Post::class)] Post $post
): Response {
    // Both $user and $post are automatically decrypted and loaded
    return $this->render('post/show.html.twig', [
        'user' => $user,
        'post' => $post,
    ]);
}
```

Generate URL with multiple encrypted parameters:

```php
$url = $this->urlGenerator->generate(
    'user_post_show',
    ['user' => $userId, 'post' => $postId],
    ['user', 'post']  // array of parameters to encrypt
);
```

In Twig:

```twig
<a href="{{ encrypted_path('user_post_show', {'user': user.id, 'post': post.id}, ['user', 'post']) }}">
    View User's Post
</a>
```

### Custom Parameter Names

By default, encrypted parameters are named after the entity parameter in your controller. You can use any parameter name:

```php
#[Route('/p/{postId}', name: 'post_short')]
public function short(
    #[EncryptedId(Post::class)] $postId
): Response {
    // Route parameter is 'postId', controller variable is also 'postId'
    // $postId contains the loaded Post entity
    return $this->render('post/show.html.twig', ['post' => $postId]);
}
```

```php
// Generate URL
$url = $this->urlGenerator->generate(
    'post_short',
    ['postId' => $post->getId()],
    'postId'  // matches the route parameter name
);
```

### Mixing Encrypted and Non-Encrypted Parameters

You can mix encrypted IDs with regular parameters:

```php
#[Route('/post/{post}/comments', name: 'post_comments')]
public function comments(
    #[EncryptedId(Post::class)] Post $post,
    int $page = 1
): Response {
    // $post is decrypted and loaded
    // $page is a regular query parameter
    return $this->render('post/comments.html.twig', [
        'post' => $post,
        'page' => $page,
    ]);
}
```

```php
$url = $this->urlGenerator->generate(
    'post_comments',
    ['post' => $postId, 'page' => 2],
    'post'  // only 'post' is encrypted, 'page' stays plain
);
// Result: /post/dGVzdF9lbmNyeXB0ZWRfZGF0YQ.../comments?page=2
```

## Security Considerations

### 1. Encryption is Non-Deterministic

Each time you encrypt an ID, you get a different encrypted value (due to random IV):

```php
$encrypted1 = $urlGenerator->generate('post_show', ['post' => 'abc123'], 'post');
$encrypted2 = $urlGenerator->generate('post_show', ['post' => 'abc123'], 'post');

// $encrypted1 !== $encrypted2 (different encrypted values)
// But both decrypt to 'abc123'
```

This prevents attackers from correlating URLs across different contexts.

### 2. Tamper Detection

The encrypted IDs include an HMAC signature. Any tampering is automatically detected:

```php
// Original URL: /post/dGVzdF9lbmNyeXB0ZWRfZGF0YQ...
// Tampered URL: /post/dGVzdF9lbmNyeXB0ZWRfZGF0YX... (changed last char)
// Result: 404 Not Found (tampering detected)
```

### 3. Access Control

**Important**: Encrypted routing does NOT provide access control. You must still implement proper authorization:

```php
#[Route('/post/{post}', name: 'post_show')]
public function show(
    #[EncryptedId(Post::class)] Post $post
): Response {
    // ✅ GOOD: Check if user can access this post
    if (!$this->isGranted('VIEW', $post)) {
        throw $this->createAccessDeniedException();
    }

    return $this->render('post/show.html.twig', ['post' => $post]);
}
```

### 4. Key Rotation

If you need to rotate encryption keys:

1. Update your environment variables
2. Old encrypted URLs will no longer work (users get 404)
3. Generate new URLs for all public links

**Recommendation**: Use `kernel.secret` which is stable across deployments.

## Error Handling

The encrypted routing system throws specific exceptions:

### Invalid Encrypted ID

When decryption fails (corrupted or tampered data):

```
NotFoundHttpException: "Invalid encrypted ID"
```

### Entity Not Found

When the decrypted ID doesn't match any entity:

```
NotFoundHttpException: "No entity "App\ParseObject\Post" found for ID "abc123""
```

Both return a 404 status code to the user.

## Performance Considerations

- **Encryption overhead**: Negligible (< 1ms per URL)
- **Database queries**: Same as regular routing (one query per entity)
- **URL length**: Encrypted IDs are longer (~88 characters in base64url)

## Examples

### Blog Application

```php
// List posts with encrypted URLs
#[Route('/blog', name: 'blog_list')]
public function list(EncryptedUrlGenerator $urlGenerator): Response
{
    $posts = $this->om->getRepository(Post::class)->findAll();

    return $this->render('blog/list.html.twig', [
        'posts' => $posts,
        'urlGenerator' => $urlGenerator,
    ]);
}

// Show single post
#[Route('/blog/{post}', name: 'blog_show')]
public function show(#[EncryptedId(Post::class)] Post $post): Response
{
    return $this->render('blog/show.html.twig', ['post' => $post]);
}
```

```twig
{# blog/list.html.twig #}
{% for post in posts %}
    <article>
        <h2>{{ post.title }}</h2>
        <a href="{{ encrypted_path('blog_show', {'post': post.id}, 'post') }}">
            Read more
        </a>
    </article>
{% endfor %}
```

### E-commerce Product Pages

```php
// Public product page with encrypted ID
#[Route('/product/{product}', name: 'product_show')]
public function show(
    #[EncryptedId(Product::class)] Product $product
): Response {
    return $this->render('product/show.html.twig', ['product' => $product]);
}

// Email campaign with encrypted product links
public function sendNewsletterEmail(Product $product, EncryptedUrlGenerator $urlGenerator): void
{
    $productUrl = $urlGenerator->generate(
        'product_show',
        ['product' => $product->getId()],
        'product',
        UrlGeneratorInterface::ABSOLUTE_URL  // Full URL for email
    );

    // Send email with $productUrl
}
```

## Comparison with Standard Routing

| Feature | Standard Routing | Encrypted Routing |
|---------|-----------------|-------------------|
| URL Length | Short | Longer (~88 chars) |
| Predictable | Yes (enumerable) | No (non-deterministic) |
| Tamper-proof | No | Yes (HMAC protected) |
| Encoding | Plain text | Base64url |
| Performance | Fast | Fast (< 1ms overhead) |
| Security | Low | High |

## Troubleshooting

### "Invalid encrypted ID" Errors

**Cause**: The encrypted ID was corrupted or tampered with

**Solution**: Generate a new URL. Old URLs cannot be recovered.

### "EncryptionService not initialized" Errors

**Cause**: Missing encryption service configuration

**Solution**: Ensure `doctrine.parse.encryption_service` is defined in services.yml (should be automatic)

### URLs Too Long

**Cause**: Encrypted IDs are ~88 characters in base64url

**Solution**: This is expected. If URL length is critical:
- Use URL shorteners for public sharing
- Store URLs in database for email campaigns
- Consider using numeric IDs with proper authorization instead

## See Also

- [Encrypted String Type](types.md#encrypted_string) - Encrypt database fields
- [Security](../../../Security/EncryptionService.php) - EncryptionService implementation
- [Symfony Routing](https://symfony.com/doc/current/routing.html) - Standard Symfony routing
