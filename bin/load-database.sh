dbname=kpa
symfony console doctrine:database:drop --force && symfony console doctrine:database:create
symfony console doctrine:migrations:migrate -n
symfony console d:schema:update --force --complete
bin/create-admins.sh
bin/console app:load-data -v
bin/console grid:index --reset
