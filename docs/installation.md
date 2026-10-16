# Install bundle in symfony application

Download bundle with composer.


```bash
composer require redking/doctrine-parse-bundle
```

The upstream `parse/php-sdk` package does not support PHP 8.4 and above yet
(see [parse-community/parse-php-sdk#528](https://github.com/parse-community/parse-php-sdk/pull/528)).
On PHP 8.4+, also require the fork that replaces it:

```bash
composer require zeliard91/parse-php-sdk
```

Your bundle should be automatically enabled if you use Flex. Otherwise, you'll need to manually enable the bundle by adding the following line in the config/bundles.php file of your project:

``` php
// config/bundles.php
<?php

return [
    // ...
    Redking\ParseBundle\RedkingParseBundle::class => ['all' => true],
];
```