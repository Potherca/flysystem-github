ARG PHP_VERSION
FROM php:${PHP_VERSION}

RUN apk add --no-cache \
    libpng \
    libpng-dev \
    ${PHPIZE_DEPS} \
  && docker-php-ext-install gd \
  && pecl install xdebug-2.5.5 \
  && docker-php-ext-enable xdebug \
  && apk del libpng-dev ${PHPIZE_DEPS}

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV XDEBUG_MODE coverage

WORKDIR /code

CMD ["php", "./vendor/bin/phpunit"]
