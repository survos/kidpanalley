<?php

use Castor\Attribute\AsTask;

use function Castor\{import, io, fs, capture, run, load_dot_env};

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
    // this is only true if sqlite!
    $database = 'kpa';
//    $process = run([...get_console(), 'doctrine:database:drop', '--force']);
//    io()->info($process->getOutput());
//    run(join(' ', get_console()) .  ' doctrine:database:create');

    $process = run([...get_console(), 'doctrine:schema:update', '--force']);
    io()->info($process->getOutput());

    $process = run([...get_console(), 'app:load', '-v']);
    io()->info($process->getOutput());

    //    $process = run(join(' ', get_console()) .  ' doctrine:database:drop --force');
//    io()->info($process->getOutput());
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

// ---------------------------------------------------------------------------
// Songs pipeline — orchestrates split → enrich → ingest end-to-end.
// Each task streams the underlying Symfony command's output (incl. progress
// bars) so slow stages like the bulk docx split are visible live.
// ---------------------------------------------------------------------------

const SONGS_DROPBOX_GMU       = '/media/tac/x10a/kpa-dropbox/Kid Pan Alley Dropbox/Songs-Recordings/-George Mason University archive working';
const SONGS_LYRICS_BY_LOCATION = SONGS_DROPBOX_GMU . '/Lyrics KPA/Lyrics by location';
const SONGS_LYRICS_INDIVIDUAL  = SONGS_DROPBOX_GMU . '/Lyrics KPA/Lyrics individual songs';
const SONGS_RESIDENCY_CURRENT  = SONGS_DROPBOX_GMU . '/!!Residency documentation/Residencies current FY25-26';
const SONGS_STAGING_DIR        = 'var/data';
const SONGS_MASTER_CSV         = 'data/master-song-list.csv';

#[AsTask(namespace: 'songs', name: 'export-csv', description: 'Re-export the Master Song List .xlsx → data/master-song-list.csv')]
function songs_export_csv(): void
{
    run([...get_console(), 'app:export-master-songlist-csv']);
}

#[AsTask(namespace: 'songs', name: 'split-location', description: 'Bulk-split Lyrics KPA/Lyrics by location/*.docx → var/data/<slug>/<slug>-lyrics.cho (slow — Dropbox drive)')]
function songs_split_location(): void
{
    io()->title('Bulk split: Lyrics by location');
    io()->note('Source is on the x10a drive — expect this to take a few minutes.');
    run([...get_console(), 'app:split-lyrics-docx', SONGS_LYRICS_BY_LOCATION, '--force', '--no-preview']);
}

#[AsTask(namespace: 'songs', name: 'split-residency', description: 'Bulk-split current residency .docx files → var/data/')]
function songs_split_residency(): void
{
    io()->title('Bulk split: Residencies current FY25-26');
    run([...get_console(), 'app:split-lyrics-docx', SONGS_RESIDENCY_CURRENT, '--force', '--no-preview']);
}

#[AsTask(namespace: 'songs', name: 'split-individual', description: 'Bulk-split Lyrics KPA/Lyrics individual songs/*.docx → var/data/')]
function songs_split_individual(): void
{
    io()->title('Bulk split: Lyrics individual songs');
    run([...get_console(), 'app:split-lyrics-docx', SONGS_LYRICS_INDIVIDUAL, '--force', '--no-preview']);
}

#[AsTask(namespace: 'songs', name: 'enrich', description: 'Drop <slug>-metadata.json next to each <slug>-lyrics.cho using the master CSV')]
function songs_enrich(): void
{
    run([...get_console(), 'app:enrich-song-dirs-from-csv', SONGS_STAGING_DIR, '--csv=' . SONGS_MASTER_CSV, '--force']);
}

#[AsTask(namespace: 'songs', name: 'ingest', description: 'Walk var/data/ and create/update Song records from .cho + metadata.json')]
function songs_ingest(): void
{
    run([...get_console(), 'app:ingest-cho-dir', SONGS_STAGING_DIR]);
}

#[AsTask(namespace: 'songs', name: 'clean', description: 'Wipe var/data/<song-slug> dirs (keeps unrelated files in var/data)')]
function songs_clean(): void
{
    $base = SONGS_STAGING_DIR;
    if (!is_dir($base)) {
        io()->warning("$base does not exist");
        return;
    }
    $removed = 0;
    foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $songDir) {
        fs()->remove($songDir);
        $removed++;
    }
    io()->success("Removed $removed song dirs from $base");
}

#[AsTask(namespace: 'songs', name: 'status', description: 'Show counts: .cho files on disk, Song records in DB')]
function songs_status(): void
{
    $base = SONGS_STAGING_DIR;
    $choCount = is_dir($base) ? count(glob($base . '/*/*-lyrics.cho') ?: []) : 0;
    $metaCount = is_dir($base) ? count(glob($base . '/*/*-metadata.json') ?: []) : 0;
    io()->definitionList(
        ['Staging dir' => $base],
        ['.cho files on disk' => $choCount],
        ['-metadata.json files' => $metaCount],
    );
    run([...get_console(), 'd:run-sql', 'SELECT count(*) as song_count FROM song']);
}

#[AsTask(namespace: 'songs', name: 'all', description: 'Run the full pipeline: export-csv → split-location → split-residency → enrich → ingest')]
function songs_all(): void
{
    songs_export_csv();
    songs_split_location();
    songs_split_residency();
    songs_enrich();
    songs_ingest();
    songs_status();
}
