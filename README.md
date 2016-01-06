Redking Parse Bundle
====================

This bundle provides object-relational mapping for the [Parse.com API](https://parse.com/docs/php/guide) data store.
It has been made by adapting code from Doctrine ORM and Doctrine MongoDB bundles so it is Doctrine compliant.

## Installation

Add bundle to composer.json


```js
{
    "require": {
        "redking/parse-bundle": "~1"
    },
    "repositories": [
        {
            "type": "vcs",
            "url":  "git@bitbucket.org:redkingteam/redkingparsebundle.git"
        }
    ]
}
```

Register the bundle

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

## Configuration

```yaml
# /app/config/config.yml

redking_parse:
    app_id: %parse.app_id%
    rest_key: %parse.rest_key%
    master_key: %parse.master_key%
    auto_mapping: true

```

## Use


### Mapping

First, define the model classes based on the parse data store.

They have to be in the `ParseObject` directory of your bundle.

ex : 

```php
<?php

namespace Acme\FooBundle\ParseObject;

class Post
{
    # define $id, $createdAt and $updatedAt
    use \Redking\ParseBundle\ObjectTrait;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var Acme\FooBundle\ParseObject\User
     */
    protected $author;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $title string
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param $author \Acme\FooBundle\ParseObject\User
     */
    public function setAuthor(\Acme\FooBundle\ParseObject\User $author)
    {
        $this->author = $author;
        return $this;
    }

    /**
     * @return \Acme\FooBundle\ParseObject\User
     */
    public function getAuthor()
    {
        return $this->author;
    }
}
```

Then, the mapping has to be defined in a YAML file (@todo : Annotation & XML Driver)

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
            name: Author
            targetDocument: User

```

You can use a specific command to generate getters and setters : 

`php app/console doctrine:parse:generate:objects AcmeFooBundle`


### Access

The way of accessing the objects is the same as in Doctrine

``` php

<?php

$om = $this->get('doctrine_parse')->getManager();


// Get all records
$posts = $om->getRepository('AcmeFooBundle:Post')->findAll();

// Get a specific record
$post = $om->getRepository('AcmeFooBundle:Post')->find('UgdfttF');


// Get the post record with the author fetched (otherwise 2 parse request are executed)
$post_fully_loaded =  $om->getRepository('AcmeFooBundle:Post')
            ->createQueryBuilder()
            ->field('id')->equals('UgdfttF')
            ->includeKey('author')
            ->getQuery()
            ->getSingleResult()
        ;


// Update
$post->setTitle('my new title');
$om->persist($post);
$om->flush();


// Remove
$om->remove($post);
$om->flush();

```

## 3rd party bundles

### FOSUserBundle integration

Extends your User model class with `FOS\UserBundle\Model\User`.

If the model does not have mapped usernameCanonical and emailCanonical, you have to override the accessors

```php
<?php

use FOS\UserBundle\Model\User as BaseUser;

class User extends BaseUser
{
    // ....
    /**
     * {@inheritdoc}
     */
    public function getUsernameCanonical()
    {
        return $this->getUsername();
    }

    /**
     * {@inheritdoc}
     */
    public function setUsernameCanonical($username)
    {
        return $this->setUsername($username);
    }

    /**
     * {@inheritdoc}
     */
    public function getEmailCanonical()
    {
        return $this->getEmail();
    }

    /**
     * {@inheritdoc}
     */
    public function setEmailCanonical($email)
    {
        return $this->setEmail($email);
    }

```

#### configuration

A custom user manager is used in order to have the proper ObjectManager injected.

The find methods using canonical fields are also override.


```yaml
# app/config/config.yml
fos_user:
    db_driver: custom
    firewall_name: main
    user_class: AcmeFooBundle\ParseObject\User
    service:
        user_manager: redking_parse.fos_user.manager

```

#### security

We have to use `simple_form` instead of `login_form` to be able to pass the authenticator.


```yaml
# app/config/security.yml
security:
    
    providers:
        fos_userbundle:
            id: fos_user.user_provider.username
    
    firewalls:
        main:
            pattern: ^/
            simple_form:
                authenticator: parse_authenticator
            logout:       true
            anonymous:    true
```
