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
const SONGS_AUDIO_JSONL        = 'data/audio.jsonl';
const SONGS_RECORDINGS_ROOT    = '/media/tac/x10a/kpa-dropbox/Kid Pan Alley Dropbox/Songs-Recordings';

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

#[AsTask(namespace: 'songs', name: 'enrich', description: 'Drop <slug>-metadata.json next to each <slug>-lyrics.cho + create dirs for CSV-only songs')]
function songs_enrich(): void
{
    run([...get_console(), 'app:enrich-song-dirs-from-csv', SONGS_STAGING_DIR, '--csv=' . SONGS_MASTER_CSV, '--force', '--create-dirs']);
}

#[AsTask(namespace: 'songs', name: 'ingest', description: 'Walk var/data/ and create/update Song records from .cho + metadata.json')]
function songs_ingest(): void
{
    run([...get_console(), 'app:ingest-cho-dir', SONGS_STAGING_DIR]);
}

#[AsTask(namespace: 'songs', name: 'scan-audio', description: 'Scan Songs-Recordings tree to data/audio.jsonl (slow — x10a drive)')]
function songs_scan_audio(): void
{
    io()->title('Scanning audio files from Songs-Recordings');
    io()->note('Source is on the x10a drive — this may take a few minutes.');
    run([...get_console(), 'import:dir', SONGS_RECORDINGS_ROOT,
        '--output=' . SONGS_AUDIO_JSONL,
        '--extensions=mp3,wav,aif,aiff,flac,m4a,ogg,wma',
        '--exclude-dirs=Cache,History',
        '--probe=2',
    ]);
}

#[AsTask(namespace: 'songs', name: 'link-audio', description: 'Read data/audio.jsonl and symlink matched audio into var/data/<slug>/')]
function songs_link_audio(): void
{
    run([...get_console(), 'app:link-audio', SONGS_AUDIO_JSONL, '--staging=' . SONGS_STAGING_DIR]);
}

#[AsTask(namespace: 'songs', name: 'ingest-audio', description: 'Create FileAsset + Audio DB records from audio files in var/data/<slug>/')]
function songs_ingest_audio(): void
{
    run([...get_console(), 'app:ingest-audio', SONGS_STAGING_DIR]);
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

#[AsTask(namespace: 'songs', name: 'status', description: 'Show counts: .cho files on disk, Song/Audio/FileAsset records in DB')]
function songs_status(): void
{
    $base = SONGS_STAGING_DIR;
    $choCount = is_dir($base) ? count(glob($base . '/*/*-lyrics.cho') ?: []) : 0;
    $metaCount = is_dir($base) ? count(glob($base . '/*/*-metadata.json') ?: []) : 0;
    $audioFileCount = is_dir($base) ? count(array_filter(
        glob($base . '/*/*.*') ?: [],
        fn ($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['mp3','wav','aif','aiff','flac','m4a'], true),
    )) : 0;
    $jsonlRows = is_file(SONGS_AUDIO_JSONL) ? count(file(SONGS_AUDIO_JSONL)) : 0;
    io()->definitionList(
        ['Staging dir' => $base],
        ['.cho files on disk' => $choCount],
        ['-metadata.json files' => $metaCount],
        ['Audio files (symlinks) on disk' => $audioFileCount],
        ['audio.jsonl rows' => $jsonlRows],
    );
    run([...get_console(), 'd:run-sql', "SELECT 'song' as entity, count(*) as cnt FROM song UNION ALL SELECT 'audio', count(*) FROM audio UNION ALL SELECT 'file_asset', count(*) FROM file_asset"]);
}

#[AsTask(namespace: 'songs', name: 'all', description: 'Run the full pipeline: export-csv → split → enrich → ingest → scan-audio → link-audio → ingest-audio')]
function songs_all(): void
{
    songs_export_csv();
    songs_split_location();
    songs_split_residency();
    songs_enrich();
    songs_ingest();
    songs_scan_audio();
    songs_link_audio();
    songs_ingest_audio();
    songs_status();
}
