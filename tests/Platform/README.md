
## How to run platform tests in docker

Set current working directory to project root.

```sh
# Init services & dependencies
- `printf "UID=$(id -u)\nGID=$(id -g)" > .env`
- `docker-compose -f tests/Platform/docker/docker-compose.yml up -d`
- `docker-compose -f tests/Platform/docker/docker-compose.yml run --rm php81 composer install`

# Test behaviour with old stringification
- `docker-compose -f tests/Platform/docker/docker-compose.yml run --rm php80 php -d memory_limit=1G vendor/bin/phpunit --group=platform`

# Test behaviour with new stringification
- `docker-compose -f tests/Platform/docker/docker-compose.yml run --rm php81 php -d memory_limit=1G vendor/bin/phpunit --group=platform`
```

You can also run utilize those containers for PHPStorm PHPUnit configuration.
