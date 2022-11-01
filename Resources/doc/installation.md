# Install bundle in symfony application

Download bundle with composer.


```bash
composer require redking/doctrine-parse-bundle
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