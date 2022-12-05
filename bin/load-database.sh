dbname=kpa
bin/console doctrine:database:drop --force && bin/console doctrine:database:create
bin/console doctrine:migrations:migrate -n
bin/create-admins.sh
bin/console app:load-data
