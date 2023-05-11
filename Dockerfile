ARG PHP_VERSION=8.2
ARG NGINX_VERSION=1.18.0


# "php base" stage
FROM php:${PHP_VERSION}-fpm-alpine AS app_php_base

# persistent / runtime deps
RUN apk add --no-cache \
        acl \
        bash \
        fcgi \
        file \
        gettext \
        git \
        freetype \
        libjpeg-turbo \
        libpng \
        nano \
    ;


ARG APCU_VERSION=5.1.18
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/bin/
RUN chmod +x /usr/bin/install-php-extensions
RUN install-php-extensions \
    gd \
    imap \
    imagick \
    intl \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    amqp \
    gd \
    imap \
    apcu \
    opcache

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN ln -s $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
COPY docker/php/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/app.ini

RUN set -eux; \
    { \
        echo '[www]'; \
        echo 'ping.path = /ping'; \
        echo 'clear_env = no'; \
    } | tee /usr/local/etc/php-fpm.d/docker-config.conf

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
# install Symfony Flex globally to speed up download of Composer packages (parallelized prefetching)
RUN set -eux; \
    composer global config --no-plugins allow-plugins.symfony/flex true; \
    composer global require "symfony/flex" --prefer-dist --no-progress --no-suggest --classmap-authoritative; \
    composer clear-cache
ENV PATH="${PATH}:/root/.composer/vendor/bin"

WORKDIR /var/www/app


# "php prod" stage
FROM app_php_base AS app_php

# prevent the reinstallation of vendors at every changes in the source code
COPY composer.json composer.lock symfony.lock ./
RUN set -eux; \
    composer install --prefer-dist --no-dev --no-scripts --no-progress --no-suggest; \
    composer clear-cache

# do not use .env files in production
COPY .env ./
RUN composer dump-env prod; \
    rm .env

# copy only specifically what we need
COPY bin bin/
COPY config config/
COPY data data/
COPY public public/
COPY migrations migrations/
COPY src src/
COPY templates templates/
COPY translations translations/
COPY assets ./assets/
COPY public ./public/
COPY webpack.config.js ./
COPY package.json yarn.lock ./

RUN set -eux; \
    mkdir -p var/cache var/log var/videos; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer run-script --no-dev post-install-cmd; \
    chmod +x bin/console; sync

RUN apk add --no-cache nodejs yarn && \
    yarn install --force && \
    mkdir -p public/build && \
    node_modules/.bin/encore production && \
    rm -rf node_modules && \
    apk del nodejs yarn --quiet && sync

VOLUME /var/www/app/var

COPY docker/php/docker-healthcheck.sh /usr/local/bin/docker-healthcheck
RUN chmod +x /usr/local/bin/docker-healthcheck

HEALTHCHECK --interval=10s --timeout=3s --retries=3 CMD ["docker-healthcheck"]

COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]


# "nginx" stage
# depends on the "php" stage above
FROM nginx:${NGINX_VERSION}-alpine AS app_nginx

ADD ./docker/nginx/nginx.conf /etc/nginx/
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/app/public

COPY --from=app_php /var/www/app/public ./

ARG PUID=1000
ARG PGID=1000

RUN if [[ -z $(getent group ${PGID}) ]] ; then \
      addgroup -g ${PGID} www-data; \
    else \
      addgroup www-data; \
    fi; \
    adduser -D -u ${PUID} -G www-data www-data
