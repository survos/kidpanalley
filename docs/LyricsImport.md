# Song Lyrics Import System

## Overview

This system imports song lyrics from Word documents (.doc and .docx) into your Symfony application.

## Architecture

The system is split into three main components:

1. **ImportSongLyricsCommand** - Symfony console command that provides the CLI interface
2. **LyricsImporter** - Service that handles the import logic and database persistence
3. **DocumentTextExtractor** - Service that extracts text from different document formats

This separation allows for:
- Easy testing of individual components
- Future expansion to handle MusicXML or other formats
- Reuse of the import logic outside of the console command

## Installation

### Prerequisites

For .doc file support, install catdoc:

```bash
# Ubuntu/Debian
sudo apt-get install catdoc

# macOS
brew install catdoc
```

For .docx file support, ensure PHPWord is installed:

```bash
composer require phpoffice/phpword
```

### Service Registration

If you're not using autowiring, register the services in `config/services.yaml`:

```yaml
services:
    App\Service\DocumentTextExtractor:
        autowire: true

    App\Service\LyricsImporter:
        autowire: true
        arguments:
            $logger: '@monolog.logger'

    App\Command\ImportSongLyricsCommand:
        autowire: true
        tags:
            - { name: 'console.command' }
```

## Usage

### Basic Import

Import all songs from a directory:

```bash
php bin/console app:import-song-lyrics /path/to/lyrics/directory
```

### Dry Run

Preview what would be imported without saving to database:

```bash
php bin/console app:import-song-lyrics /path/to/lyrics/directory --dry-run
```

### Skip Existing Songs

Only import songs that don't already have lyrics:

```bash
php bin/console app:import-song-lyrics /path/to/lyrics/directory --skip-existing
```

## File Format Expectations

### .doc files (legacy)

- May contain multiple songs separated by form feed characters (^L)
- Each song should have:
    - First line: Song title
    - Optional second line: "by [Author Name]"
    - Remaining lines: Song lyrics

### .docx files (modern)

- One song per file
- Filename (without extension) is used as the song title
- All text in the document becomes the lyrics

## Current Behavior

1. **Text Extraction**: Extracts plain text from documents, preserving line breaks
2. **Normalization**: Removes double line breaks
3. **Title Matching**: Finds or creates songs based on title
4. **Validation**: Validates the Song entity before persisting

## Future Enhancements

The `DocumentTextExtractor` service includes placeholder methods for:

- **MusicXML Support**: `extractFromMusicXml()` - Parse structured music XML files
- **Structured Parsing**: `convertToStructuredFormat()` - Analyze indentation to identify verses, refrains, and bridges

## Logging

The system logs to your configured Symfony logger:

- **Warning**: When directories don't exist or songs have no title
- **Error**: When files can't be processed
- **Info**: Import completion statistics

## Error Handling

The command will:
- Continue processing other files if one file fails
- Display errors in the console output
- Return appropriate exit codes (0 for success, 1 for failure)
- Log all errors for debugging

## Statistics

After each import, you'll see:
- **Processed**: Total number of files examined
- **Imported**: Number of songs successfully imported/updated
- **Skipped**: Number of songs skipped (when using --skip-existing)
- **Errors**: Number of files that failed to process

## Example Output

```
Importing Song Lyrics
=====================

Source directory: /var/www/songs

Processing: amazing-grace.docx
Processing: silent-night.doc
Processing: hymn-collection.doc

 [OK] Import completed successfully!
      Processed: 3 files
      Imported: 15 songs
      Skipped: 0 songs
      Errors: 0
```

## Troubleshooting

### catdoc not found

If you get an error about catdoc:

```bash
sudo apt-get install catdoc  # Linux
brew install catdoc          # macOS
```

### Memory issues with large files

For very large files, you may need to increase PHP memory:

```bash
php -d memory_limit=512M bin/console app:import-song-lyrics /path/to/directory
```

### Validation errors

Check your Song entity's validation constraints. The importer will reject songs that don't pass validation.

## Testing

See `tests/Service/LyricsImporterTest.php` for unit tests.

Run tests:

```bash
php bin/phpunit tests/Service/LyricsImporterTest.php
```
