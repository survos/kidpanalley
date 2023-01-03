#!/usr/bin/env bash

APPLICATION=${CI_PROJECT_DIR:-..}
APP_ENV=${APP_ENV:-prod}
ENVIRONMENT=${ENVIRONMENT:-local}
DOCKER_ENV=${DOCKER_ENV:-prod}
COMPOSE_PROJECT_NAME=kpa
GIT_TAG=${CI_COMMIT_TAG:-$(git describe --tags --exact-match || true)}
GIT_BRANCH=${CI_COMMIT_BRANCH:-$(git rev-parse --abbrev-ref HEAD)}
DATE_ISO=$(date -I'seconds')
VERSION=${GIT_TAG:-$GIT_BRANCH}-${DATE_ISO}
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@rabbitmq:5672/%2f/messages

echo "APP_ENV: ${APP_ENV} VERSION: ${VERSION}"

TAG=${CI_COMMIT_REF_SLUG:-latest}
PUID=$(id -u)
PGID=$(id -g)

REGISTRY=${CI_REGISTRY:-hub.docker.com}
REGISTRY_IMAGE=${CI_REGISTRY_IMAGE:-hub.docker.com/project}
REGISTRY_USER=${CI_REGISTRY_USER:-user}
REGISTRY_PASSWORD=${CI_REGISTRY_PASSWORD:-***}

case "$DOCKER_ENV" in
    "prod")
        COMPOSE_FILE=docker-compose.prod.yml
        ;;
    "test")
        COMPOSE_FILE=docker-compose.test.yml
        ;;
esac

# docker env file

sed -e" \
    s#^DOCKER_ENV=.*#DOCKER_ENV=$DOCKER_ENV#; \
    s#APP_ENV=.*#APP_ENV=$APP_ENV#; \
    s#ENVIRONMENT=.*#ENVIRONMENT=$ENVIRONMENT#; \
    s#APPLICATION=.*#APPLICATION=$APPLICATION#; \
    s#PUID=.*#PUID=$PUID#; \
    s#PGID=.*#PGID=$PGID#; \
    s#REGISTRY=.*#REGISTRY=$REGISTRY#; \
    s#REGISTRY_IMAGE=.*#REGISTRY_IMAGE=$REGISTRY_IMAGE#; \
    s#REGISTRY_USER=.*#REGISTRY_USER=$REGISTRY_USER#; \
    s#REGISTRY_PASSWORD=.*#REGISTRY_PASSWORD=$REGISTRY_PASSWORD#; \
    s#TAG=.*#TAG=$TAG#; \
    s#COMPOSE_FILE=.*#COMPOSE_FILE=$COMPOSE_FILE#; \
    s#COMPOSE_PROJECT_NAME=.*#COMPOSE_PROJECT_NAME=$COMPOSE_PROJECT_NAME#; \
    s#RABBITMQ_HOST=.*#RABBITMQ_HOST=$RABBITMQ_HOST#; \
    s#RABBITMQ_PORT=.*#RABBITMQ_PORT=$RABBITMQ_PORT#; \
    s#RABBITMQ_MANAGEMENT_PORT=.*#RABBITMQ_MANAGEMENT_PORT=$RABBITMQ_MANAGEMENT_PORT#; \
    s#RABBITMQ_USER=.*#RABBITMQ_USER=$RABBITMQ_USER#; \
    s#RABBITMQ_PASSWORD=.*#RABBITMQ_PASSWORD=$RABBITMQ_PASSWORD#; \
" .env.dist > .env

# app env file

sed -e" \
    s#^APP_ENV=.*#APP_ENV=$APP_ENV#; \
    s#^VERSION=.*#VERSION=$VERSION#; \
    s#^MESSENGER_TRANSPORT_DSN=.*#MESSENGER_TRANSPORT_DSN=$MESSENGER_TRANSPORT_DSN#; \
" ${APPLICATION}/.env > .app_env


if [ ! -z "$DATABASE_URL" ] ; then
    sed -i " \
        s#^DATABASE_URL=.*#DATABASE_URL=$DATABASE_URL#; \
    " .app_env
fi

if [ ! -z "$MESSENGER_TRANSPORT_DSN" ] ; then
    sed -i " \
        s#^MESSENGER_TRANSPORT_DSN=.*#MESSENGER_TRANSPORT_DSN=$MESSENGER_TRANSPORT_DSN#; \
    " .app_env
fi

if [ ! -z "$DOMAIN" ] ; then
    sed -i " \
        s#^DOMAIN=.*#DOMAIN=$DOMAIN#; \
    " .app_env
fi

if [ ! -z "$YOUTUBE_CHANNEL" ] ; then
    sed -i " \
        s#^YOUTUBE_CHANNEL=.*#YOUTUBE_CHANNEL=$YOUTUBE_CHANNEL#; \
    " .app_env
fi

if [ ! -z "$YOUTUBE_API_KEY" ] ; then
    sed -i " \
        s#^YOUTUBE_API_KEY=.*#YOUTUBE_API_KEY=$YOUTUBE_API_KEY#; \
    " .app_env
fi
