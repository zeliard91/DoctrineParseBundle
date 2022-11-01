# Form Types

This bundle has a `FormTypeGuesser` who guess which symfony core form type to use for each mapped attributes.

It provides some new form types for specific mapping types : 

## ObjectType

The type `ObjectType` has the same behavior as Doctrine `EntityType`.

It has to be used for associations : 

```php
<?php

namespace App\Form\Type;

use App\ParseObject\User;
use Redking\ParseBundle\Form\Type\ObjectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class PostType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title')
            ->add('author', ObjectType::class, [
                'required' => true,
                'class' => User::class // optionnal
                ])
        ;
    }
}

```


for One-To-Many associations, you just have to add the option `multiple` with the value `true`.


## ParseFileType

This form type (who inherits core `FileType`) handle the uploads to the Parse server.

If you want to hard code the file name, you can use the `force_name` option : 

```php
    use Redking\ParseBundle\Form\Type\ParseFileType;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', ParseFileType::class, [
                'force_name' => 'avatar.jpg'
                ])
            ;
    }
```

Custom constraints `ParseFile` and `ParseFileImage` can also be used.

They inherit respectivly from core Symfony constraints [`File`](https://symfony.com/doc/current/reference/constraints/File.html) and [`Image`](https://symfony.com/doc/current/reference/constraints/Image.html) and accept the same options.

Ex:

```php

namespace App\ParseObject;

use Redking\ParseBundle\Mapping\Annotations as ORM;
use Redking\ParseBundle\Validator\Constraints as ParseAssert;

class User
{
    // ...

    /**
     * @ORM\Field(type="file")
     * @ParseAssert\ParseFileImage(maxSize="2M")
     */
    protected $avatar;
}
```

## GeoPointType

This form type handles `geopoint` field types by providing 2 text inputs for latitude and longitude

```php
    use Redking\ParseBundle\Form\Type\GeoPointType;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('location', GeoPointType::class)
            ;
    }
```
