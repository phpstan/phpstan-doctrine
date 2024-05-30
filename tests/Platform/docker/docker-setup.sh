set -ex \
  && apt update \
  && apt install -y bash zip libpq-dev libsqlite3-dev \
  && pecl install xdebug mongodb \
  && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
  && docker-php-ext-install pdo mysqli pgsql pdo_mysql pdo_pgsql pdo_sqlite \
  && docker-php-ext-enable xdebug mongodb
