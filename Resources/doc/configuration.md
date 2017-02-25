# Configuration

Basic configuration is simple, here is the minimal required informations : 

```yaml
# /app/config/config.yml

redking_parse:
    app_id: %parse.app_id%
    rest_key: %parse.rest_key%
    master_key: %parse.master_key%
    server_url: %parse.server_url%
    auto_mapping: true

```

You can see all the different configuration options with : 

```bash
php app/console config:dump-reference redking_parse
```
