dbname=kpa

#echo "create user main WITH ENCRYPTED PASSWORD 'main';" | sudo -u postgres psql
echo "drop database if exists $dbname; create database $dbname; grant all privileges on database $dbname to main; " | sudo -u postgres psql
echo "CREATE EXTENSION pg_trgm;" | sudo -u postgres psql -d $dbname
echo "CREATE EXTENSION hstore;" | sudo -u postgres psql -d $dbname
echo "database $dbname now ready for migrations or restore"

#bin/console doctrine:schema:update --force
bin/console doctrine:migrations:migrate -n
bin/create-admins.sh
bin/console app:load-data
