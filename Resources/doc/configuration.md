# Configuration

Basic configuration is simple, here is the minimal required informations : 

Add your Parse Server informations in environment variables

```sh
# .env.local

PARSE_SERVER_URL=http://127.0.0.1:1337
PARSE_APP_ID=foo
PARSE_MASTER_KEY=bar
PARSE_REST_KEY=
PARSE_MOUNT_PHP=parse

```

and link them in the package configuration file

```yaml
# /config/packages/redking_parse.yml

redking_parse:
    app_id: '%env(PARSE_APP_ID)%'
    rest_key: '%env(PARSE_REST_KEY)%'
    master_key: '%env(PARSE_MASTER_KEY)%'
    server_url: '%env(PARSE_SERVER_URL)%'
    mount_path: '%env(PARSE_MOUNT_PHP)%'
    auto_mapping: true

    # Specify the location of your models
    mappings:
        App:
            is_bundle: false
            dir: '%kernel.project_dir%/src/ParseObject'
            prefix: 'App\ParseObject'
            alias: App

            # if you want to use Yaml mapping
            # type: yml
            # dir: "%kernel.project_dir%/config/doctrine"
```

You can see all the different configuration options with : 

```bash
php bin/console config:dump-reference redking_parse
```
