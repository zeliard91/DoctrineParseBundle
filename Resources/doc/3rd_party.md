# Third Party Bundles

## FOSUserBundle

You can handle authentication in your symfony app with FOSUserBundle with this implementation : 

### Model

Extends your User model class with `FOS\UserBundle\Model\User`.

If the model does not have mapped usernameCanonical and emailCanonical, you have to override the accessors

```php
<?php

namespace AcmeFooBundle\ParseObject;

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
}
```


### Configuration

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

### Security

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


## Doctrine Fixtures

[Doctrine Fixtures](https://github.com/doctrine/data-fixtures) can be loaded with the command `doctrine:parse:fixtures:load`.

The files have to be defined in directories `DataFixtures/Parse`.
