
## How to run platform tests in docker

Set current working directory to project root.

```sh
# Init services & dependencies
- `printf "UID=$(id -u)\nGID=$(id -g)" > .env`
- `docker-compose -f tests/Platform/docker/docker-compose.yml up -d`

# Test behaviour with old stringification
- `docker-compose -f tests/Platform/docker/docker-compose.yml run --rm php80 composer update`
- `docker-compose -f tests/Platform/docker/docker-compose.yml run --rm php80 php -d memory_limit=1G vendor/bin/phpunit --group=platform`

# Test behaviour with new stringification
- `docker-compose -f tests/Platform/docker/docker-compose.yml run --rm php81 composer update`
- `docker-compose -f tests/Platform/docker/docker-compose.yml run --rm php81 php -d memory_limit=1G vendor/bin/phpunit --group=platform`
```

You can also run utilize those containers for PHPStorm PHPUnit configuration.

Since the dataset is huge and takes few minutes to run, you can filter only functions you are interested in:
```sh
`docker-compose -f tests/Platform/docker/docker-compose.yml run --rm php81 php -d memory_limit=1G vendor/bin/phpunit --group=platform --filter "AVG"`
```
