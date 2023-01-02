#!/usr/bin/env bash

set -e

composer install --prefer-dist --no-interaction --no-scripts --no-progress

echo "Waiting for db to be ready..."
until bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
  sleep 1
done
bin/console doctrine:migrations:migrate --no-interaction || true

#TODO: install phpunit
#vendor/bin/simple-phpunit -c ./phpunit.xml.dist
