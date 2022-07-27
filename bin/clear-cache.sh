ENV=${1:-dev} # dev, prod, etc.
# bin/console cache:pool:clear cache.app

bin/console cache:pool:clear cache.serializer cache.annotations cache.property_info \
  api_platform.cache.route_name_resolver \
  api_platform.cache.metadata.resource
#  api_platform.cache.metadata.property
#bin/console cache:pool:clear cache.system

#  cache.doctrine.orm.default.query \
#bin/console cache:pool:clear  cache.annotations
#bin/console cache:pool:clear  cache.property_info
##bin/console cache:pool:clear  cache.doctrine.orm.default.metadata
#bin/console cache:pool:clear  cache.doctrine.orm.default.result
#bin/console cache:pool:clear  cache.doctrine.orm.default.query
#bin/console cache:pool:clear api_platform.cache.route_name_resolver
#bin/console cache:pool:clear                                api_platform.cache.identifiers_extractor
#bin/console cache:pool:clear                                api_platform.cache.subresource_operation_factory
#bin/console cache:pool:clear                                api_platform.cache.metadata.resource
#bin/console cache:pool:clear                                api_platform.cache.metadata.property

# bin/console doctrine:query:sql "delete from messenger_messages"

# bin/console cache:clear
redis-cli FLUSHALL
#  rm -Rf var/cache/$ENV && bin/console cache:clear --no-warmup --env=$ENV && bin/console cache:warmup --env=$ENV
