# Install bundle in symfony 2/3 application

Download bundle with composer.


```bash
composer require redking/parse-bundle
```

Register the bundle in your app.

``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Redking\ParseBundle\RedkingParseBundle(),

    );
}
```