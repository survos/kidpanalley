<?php

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\capture;
use function Castor\load_dot_env;
use function Castor\run;

#[AsTask(description: 'Welcome to Castor!')]
function hello(): void
{
    $currentUser = capture('whoami');

    io()->title(sprintf('Hello %s!', $currentUser));
}

#[AsTask(description: 'Start local docker services')]
function start_services()
{
    run('sudo docker run --rm --name meili -d -p 7700:7700 -v $(pwd)/../meili_data:/meili_data getmeili/meilisearch:latest meilisearch');

}



#[AsTask(description: 'Purge and re-create the database')]
function reset_database()
{
    $database = 'kpa';
    $process = run([...get_console(), 'doctrine:database:drop', '--force'], allowFailure: true);
    io()->info($process->getOutput());

    $process = run([...get_console(), 'doctrine:schema:update', '--force', '--complete'], allowFailure: true);
    io()->info($process->getOutput());

    $process = run([...get_console(), 'app:load-data', '-v'], allowFailure: true);
    io()->info($process->getOutput());

    //    $process = run(join(' ', get_console()) .  ' doctrine:database:drop --force', allowFailure: true);
//    io()->info($process->getOutput());
    run(join(' ', get_console()) .  ' doctrine:database:create');
//symfony console doctrine:database:drop --force && symfony console doctrine:database:create
//symfony console doctrine:migrations:migrate -n
//#symfony console d:schema:update --force --complete
//bin/create-admins.sh
//symfony console app:load-data -v


}

function get_console(): array
{
    $env = load_dot_env();
    $console =  ($env['APP_ENV'] === 'prod') ? ['bin/console']: ['symfony', 'console'];
    return $console;
}
