#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi

if [ "${WAIT_APP:-0}" -eq 1 ]; then
    APP_HOST="${APP_HOST:-app}"
    WAIT=0; echo "Waiting until ${APP_HOST} runtime-init is completed"
    while ! nc -z ${APP_HOST} 9000; do [ "$WAIT" -gt 600 ] && echo "Error: runtime-init timeout" && exit 1 || sleep 1 && WAIT=$(($WAIT + 1)); done
    echo "runtime-init is completed. Starting"
fi

if [ "${WAIT_RABBIT:-0}" -eq 1 ]; then
    RABBITMQ_HOST="${RABBITMQ_HOST:-rabbitmq}"
    WAIT=0; echo "Waiting for ${RABBITMQ_HOST} to start"
    while ! nc -z ${RABBITMQ_HOST} ${RABBITMQ_PORT}; do [ "$WAIT" -gt 60 ] && echo "Error: RabbitMQ timeout" && exit 1 || sleep 1 && WAIT=$(($WAIT + 1)); done
    echo "RabbitMQ is up"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    PHP_INI_RECOMMENDED="$PHP_INI_DIR/php.ini-production"
    if [ "$APP_ENV" != 'prod' ]; then
        PHP_INI_RECOMMENDED="$PHP_INI_DIR/php.ini-development"
    fi
    ln -sf "$PHP_INI_RECOMMENDED" "$PHP_INI_DIR/php.ini"

    mkdir -p var/cache var/log
    setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
    setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var

    bin/console debug:container --env-vars

    if [ "$APP_ENV" != 'prod' ]; then
        composer install --prefer-dist --no-progress --no-suggest --no-interaction
    fi

    echo "Waiting for db to be ready..."
    until bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
        sleep 1
    done

    if ls -A migrations/*.php > /dev/null 2>&1; then
        bin/console doctrine:migrations:migrate --no-interaction
    fi
fi

exec docker-php-entrypoint "$@"
