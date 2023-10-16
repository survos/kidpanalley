dbname=kpa
synfony console doctrine:database:drop --force && bin/console doctrine:database:create
symfony console doctrine:migrations:migrate -n
bin/create-admins.sh
symfony console app:load-data
