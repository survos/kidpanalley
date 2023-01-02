## Run in prod mode
```
export DATABASE_URL="postgresql://main:main@172.17.0.1:5432/kpa?serverVersion=14.5\&charset=utf8"
export YOUTUBE_CHANNEL=X
export YOUTUBE_API_KEY=Y
DOCKER_ENV=prod APP_ENV=prod bin/copy-env.sh
bin/build.sh

#create network for traefik
docker network create --driver=overlay --attachable webproxy

docker-compose down
docker-compose up -d
open http://localhost:8069

#cleanup
docker-compose down --remove-orphans
```

## Tests
```
cd docker
DOCKER_ENV=test APP_ENV=test bin/copy-env.sh
bin/build.sh
docker-compose run --rm php sh -c "bin/run-tests.sh"
#cleanup mysql
docker-compose down --remove-orphans
```

### Push
```
bin/push.sh
```

### Run
```
eval $(docker-machine env scw0)
echo 'password' | docker login registry.optdeal.com -u karser --password-stdin
docker-compose pull
docker-compose down --remove-orphans; docker-compose up -d
docker-compose logs -f

#clear cache (a bug)
docker exec -it -u app rmv_app_1 sh
rm -rf ./var/cache/*
```

### ERROR: Network webproxy declared as external, but could not be found.
```
docker network create --driver=overlay --attachable webproxy
```
