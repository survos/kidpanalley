dbname=kpa
symfony console doctrine:database:drop --force && symfony console doctrine:database:create
symfony console doctrine:migrations:migrate -n
symfony console d:schema:update --force --complete
bin/create-admins.sh
symfony console app:load-data -v
symfony console grid:index --reset
