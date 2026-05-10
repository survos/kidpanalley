# Songs Import Pipeline

End-to-end flow that turns the Dropbox **Songs-Recordings** tree into one-folder-per-song on disk and `Song` + `FileAsset` records in the database.

## Source

```
/media/tac/x10a/kpa-dropbox/Kid Pan Alley Dropbox/Songs-Recordings/
```

The richest subtree for lyrics:

```
Songs-Recordings/-George Mason University archive working/Lyrics KPA/
├── Lyrics individual songs/    # mostly one-file-per-title; some multi-song collections
├── Lyrics by location/         # ~90 school/residency subdirs + many <School><Year>Lyric.docx files (motherload of multi-song .docx)
├── album lyrics/
├── kpa-reisler songbook/
└── …
```

Audio (`.wav`, `.mp3`, `.aif`, …) and charts (`.pdf`, `.musx`, …) are scattered throughout the rest of the tree.

## Stage 1 — Scan to JSONL

The directory walker has been generalized into [`survos/import-bundle`](../vendor/survos/import-bundle/src/Command/ImportDirCommand.php). Use it for every scan — never write a custom finder loop.

```bash
bin/console import:dir \
  "/media/tac/x10a/kpa-dropbox/Kid Pan Alley Dropbox/Songs-Recordings/" \
  --output=data/songs-recordings.jsonl \
  --extensions=doc,docx,wav,mp3,aif,aiff,flac,pdf,musx,mus,sib,mxl,musicxml,xml,song,plj,m4a,rtf,txt,pages \
  --probe=0
```

- Each emitted row has `id, root_id, root_path, path, relative_path, filename, basename, dirname, extension, tags, probe_level`.
- `--probe=1` adds size/mime/checksum (slower); `--probe=2` adds ffprobe + docx metadata.
- The JSONL is the durable artifact for downstream stages — re-run later stages without rescanning.

## Stage 2 — Split multi-song lyrics + stage

Walk `data/songs-recordings.jsonl`. For each lyrics file (`doc, docx, rtf, txt, pages`):

- **`.doc`** — split on form feed (`\f`); first non-blank line of each chunk is the title, optional second line `by …` is credit. Existing logic in [`LyricsImporter::importFromDocFile`](../src/Services/LyricsImporter.php#L120).
- **`.docx`** — needs new logic. Likely heuristics, in priority order:
  1. PHPWord heading style (`Heading 1` / `Heading 2`) marks song title boundaries.
  2. Page break (`<w:br w:type="page"/>`) between songs.
  3. Two+ blank lines + an all-caps or Title-Case line that matches the canonical title list.

Each detected song produces a folder on the **same x10a drive** (so symlinks survive). The on-disk lyric format is **ChordPro `.cho`** — the project already standardizes on this (see `src/Entity/Lyrics.php`, `\ChordPro\Parser` composer dep, `FetchChoFilesCommand`):

```
…/Songs-Recordings/-Songs-By-Title/<slugified-title>/
└── lyrics.cho              # ChordPro: {title:}, {artist:}, {year:}, {school:}, {start_of_chorus}, …
└── lyrics.original.docx    # optional symlink back to source for traceability
```

`.cho` is plain text, diff-friendly, hand-editable, small enough that `Song.lyrics` / `Lyrics.text` (both `TEXT` columns; Postgres TOAST handles compression past ~2KB) holds the same content verbatim at ingest time.

**Structure preservation is the real work.** A docx → plain-text dump loses chorus/verse boundaries forever. The splitter must walk PHPWord style runs:

| docx signal | ChordPro output |
| --- | --- |
| `Heading 1` style or page break | song title boundary → new file, write `{title: …}` |
| Italic / centered / indented block, OR a line "CHORUS:" / "Refrain:" | wrap with `{start_of_chorus}` / `{end_of_chorus}` |
| Plain paragraph runs | verse text (verses are ChordPro's default; explicit `{start_of_verse}` only if needed) |
| Filename context (`<School><Year>Lyric.docx`) | `{school: …}` and `{year: …}` directives on every song in the file |

Lyrics-only files have no chord notation, so we don't emit `[C]`/`[G]` inline-chord tokens — the .cho stays directive-and-text only.

The staging tree is the human-auditable artifact — eyeball the `.cho` files before stage 4.

## Stage 3 — Symlink related files

For every non-lyrics row in the JSONL, run [`SongMatcher::normalizeTitle`](../src/Service/SongMatcher.php) on the filename to get a canonical title, then symlink the source file into the matching `<staging>/<title>/` folder. Multiple variants live side-by-side:

```
<staging>/frogs-and-polliwogs/
├── lyrics.txt
├── frogs and polliwogs - guitar.mp3   -> /media/tac/x10a/.../guitar.mp3
├── frogs and polliwogs - vocals.wav   -> /media/tac/x10a/.../vocals.wav
└── frogs and polliwogs.pdf            -> /media/tac/x10a/.../chart.pdf
```

## Stage 4 — Ingest staging tree to DB

Run `import:dir` again **on the staging tree**, then group + persist:

```bash
bin/console import:dir <staging>/ --output=data/songs-by-title.jsonl --probe=1
bin/console app:songs-tree data/songs-by-title.jsonl --persist
```

`app:songs-tree` (see [`SongsTreeCommand`](../src/Command/SongsTreeCommand.php)) groups by canonical title via `SongMatcher`, deduplicates by `<stem>.<type>`, and creates/updates `Song` records. Add a `FileAsset` persistence pass alongside it.

Lyrics ingestion: for each `lyrics.cho` row, read the file → assign to `Lyrics::text` → parser auto-fills `title`, `artist`, `chordProData` via the property hooks. Mirror to `Song.lyrics` so search hits (`MeiliIndex.searchable: ['lyrics','title']`) keep working at the Song level.

## Re-running

- Stage 1 is the slow one (large drive). Re-run only when source changes.
- Stages 2–4 read JSONL + write to staging tree + DB; safe to re-run idempotently.

## Open questions / future work

- `.docx` splitter heuristics need to be tuned against real samples (start with `Christmas Songbook KPA.docx` and `BurnleyMoran2012Lyric.docx`).
- Title canonicalization across school+year contexts — same song sung at multiple residencies should collapse to one `Song` record. `SongMatcher::warmCache` + `findOrCreate` already handles this for `app:songs-tree`.
- Decide whether to keep extracted lyrics as `lyrics.txt` (clean text) or preserve `lyrics.docx` (round-trip lossless). Current proposal: emit both, source-of-truth is `lyrics.txt`.

## Idea: embed metadata in .cho files

The standard/well-known directives are a fairly small set:

{title:} (or {t:})
{subtitle:} (or {st:})
{artist:} (or {a:}) — multiple allowed
{composer:}
{lyricist:}
{copyright:}
{album:}
{year:}
{key:}
{time:} (time signature)
{tempo:}
{capo:}
{duration:}

Then there's a {meta: name value} directive specifically designed for arbitrary custom metadata, e.g. {meta: kpa_id 1247} or {meta: paul_notes "written on the porch, 2003"}. ChordPro tools that don't recognize a custom name will just pass it through or ignore it rather than error out — which is the behavior you want for a long-lived archive format.
For KPA specifically, a few things worth thinking about as you decide what to put in the .cho vs. keep in the database:
The .cho file is great as the canonical source of truth for the song itself — anything intrinsic that should travel with the file if it's exported, shared with GMU/TIND, or handed to another musician. Things like kpa_id, original key, recording references, co-writer credits, year written, kid collaborator names (if Paul wants those preserved), source recording filename, even a {meta: chordpro_version} for your own pipeline tracking.
Things that are more about your processing pipeline state — OCR confidence, indexing status, last-modified timestamps, audio fingerprint hashes, Meilisearch doc IDs — those probably belong in the database/sidecar, not in the .cho itself, since they're operational rather than archival.
One gotcha: not all ChordPro parsers handle {meta:} the same way. ChordPro 6+ (the Johan Vromans reference implementation) handles it well. If you're using a lighter parser in PHP/JS, double-check that custom {meta:} directives round-trip cleanly through your read→edit→write cycle so you don't accidentally strip Paul's annotations.
Are you leaning toward {meta: key value} for everything custom, or do you want to define your own directive names like {kpa_id: 1247}? Both work, but {meta:} is the more conventional path and less likely to collide with future ChordPro spec additions.
