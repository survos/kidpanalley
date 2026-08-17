<?php

declare(strict_types=1);

namespace App\Schema;

use App\Entity\Audio;
use App\Entity\Song;
use App\Entity\SongLyrics;
use App\Entity\Video;
use Spatie\SchemaOrg\AudioObject;
use Spatie\SchemaOrg\CreativeWork;
use Spatie\SchemaOrg\EducationalOrganization;
use Spatie\SchemaOrg\MusicComposition;
use Spatie\SchemaOrg\MusicRecording;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\Person;
use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\VideoObject;
use Spatie\SchemaOrg\WebPage;
use Spatie\SchemaOrg\WebSite;
use Survos\SchemaOrgBundle\Graph\SchemaOrgGraph;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Song → JSON-LD, for survos/schema-org-bundle.
 *
 * The type split is the point of this mapping. A Kid Pan Alley song is a
 * MusicComposition — the work the students and songwriters wrote — and each file
 * in the archive is a separate MusicRecording of it, wrapping an AudioObject for
 * the bytes. Modelling the song as a single MusicRecording would collapse "the
 * song" and "the seven takes of the song we happen to hold" into one node, which
 * is exactly the distinction an archive exists to keep.
 */
final readonly class SongSchema
{
    public function __construct(
        private SchemaOrgGraph $schemaOrg,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param list<Audio>       $audios
     * @param list<SongLyrics>  $songLyrics
     */
    public function addToGraph(Song $song, string $siteUrl, array $audios, array $songLyrics): void
    {
        $siteUrl = rtrim($siteUrl, '/');
        $canonicalUrl = $this->urlGenerator->generate(
            'song_show',
            ['songId' => $song->id],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $websiteId = $siteUrl . '/#website';
        $website = $this->schemaOrg->getOrCreate(WebSite::class, $websiteId);
        $website
            ->identifier($websiteId)
            ->url($siteUrl)
            ->name('Kid Pan Alley');

        $compositionId = $canonicalUrl . '#song';
        $composition = Schema::musicComposition()
            ->identifier($compositionId)
            ->url($canonicalUrl);

        if ($this->hasText($song->title)) {
            $composition->name(trim($song->title));
        }

        if ($this->hasText($song->description)) {
            $composition->description(trim($song->description));
        }

        // date is the precise field, year the fallback — schema.org accepts either a
        // full date or a bare year for dateCreated, so publish the best one we have.
        if (null !== $song->date) {
            $composition->dateCreated($song->date->format('Y-m-d'));
        } elseif (null !== $song->year) {
            $composition->dateCreated((string) $song->year);
        }

        $composers = array_map(
            fn (string $name) => $this->person($siteUrl, $name)->referenced(),
            $song->getWritersArray(),
        );
        if ([] !== $composers) {
            $composition->composer($composers);
        }

        $publishers = array_map(
            fn (string $name) => $this->organization($siteUrl, $name)->referenced(),
            $song->getPublishersArray(),
        );
        if ([] !== $publishers) {
            $composition->publisher($publishers);
        }

        // The school's students co-wrote the song, so the school is a contributor —
        // not the publisher (that's the label) and not the author (those are the named
        // writers). `school` is a plain name column, hence a slug for the @id.
        if ($this->hasText($song->school)) {
            $composition->contributor($this->school($siteUrl, $song->school)->referenced());
        }

        $this->addLyrics($songLyrics, $composition, $compositionId);

        $recordings = array_map(
            fn (Audio $audio) => $this->recording($audio, $canonicalUrl, $compositionId)->referenced(),
            $audios,
        );
        if ([] !== $recordings) {
            $composition->recordedAs($recordings);
        }

        // Videos are performances of the song rather than recordings of it in the
        // MusicRecording sense, so they hang off workExample (CreativeWork) instead of
        // recordedAs, which schema.org restricts to MusicRecording.
        $videos = [];
        foreach ($song->videos as $video) {
            if ($this->hasText($video->youtubeId)) {
                $videos[] = $this->video($video, $canonicalUrl)->referenced();
            }
        }
        if ([] !== $videos) {
            $composition->workExample($videos);
        }

        $webPageId = $canonicalUrl . '#webpage';
        $webPage = $this->schemaOrg->getOrCreate(WebPage::class, $webPageId);
        $webPage
            ->identifier($webPageId)
            ->url($canonicalUrl)
            ->isPartOf($website->referenced())
            ->mainEntity($composition->referenced());

        if ($this->hasText($song->title)) {
            $webPage->name(trim($song->title));
        }

        $composition->mainEntityOfPage($webPage->referenced());

        $this->schemaOrg->add($composition);
    }

    /**
     * Lyrics come from the matched lyric DOCUMENTS, never from Song::$lyrics.
     *
     * Song::$lyrics is ChordPro — chord brackets and {start_of_chorus} directives —
     * so publishing it as schema.org lyrics would emit a chord chart claiming to be
     * lyric text. SongLyrics::$lyricsText is already plain text. When we only have
     * the ChordPro, the property is omitted rather than approximated.
     *
     * @param list<SongLyrics> $songLyrics
     */
    private function addLyrics(array $songLyrics, MusicComposition $composition, string $compositionId): void
    {
        foreach ($songLyrics as $link) {
            if (!$this->hasText($link->lyricsText)) {
                continue;
            }

            $lyricsId = $compositionId . '-lyrics';
            $lyrics = $this->schemaOrg->getOrCreate(CreativeWork::class, $lyricsId);
            $lyrics
                ->identifier($lyricsId)
                ->text(trim($link->lyricsText));

            $composition->lyrics($lyrics->referenced());

            return;
        }
    }

    private function recording(Audio $audio, string $canonicalUrl, string $compositionId): MusicRecording
    {
        $recordingId = $canonicalUrl . '#recording-' . $audio->id;
        $recording = $this->schemaOrg->getOrCreate(MusicRecording::class, $recordingId);
        $recording
            ->identifier($recordingId)
            ->url($this->urlGenerator->generate('audio_show', ['id' => $audio->id], UrlGeneratorInterface::ABSOLUTE_URL))
            ->recordingOf(Schema::musicComposition()->identifier($compositionId)->referenced())
            ->audio($this->audioObject($audio, $canonicalUrl)->referenced());

        if ($this->hasText($audio->title)) {
            $recording->name(trim($audio->title));
        }

        $duration = $this->isoDuration($audio->fileAsset->duration);
        if (null !== $duration) {
            $recording->duration($duration);
        }

        return $recording;
    }

    private function audioObject(Audio $audio, string $canonicalUrl): AudioObject
    {
        $audioId = $canonicalUrl . '#audio-' . $audio->id;
        $audioObject = $this->schemaOrg->getOrCreate(AudioObject::class, $audioId);
        $audioObject
            ->identifier($audioId)
            ->contentUrl($this->urlGenerator->generate('audio_file', ['id' => $audio->id], UrlGeneratorInterface::ABSOLUTE_URL));

        $file = $audio->fileAsset;

        if ($this->hasText($file->mimeType)) {
            $audioObject->encodingFormat($file->mimeType);
        }

        if (null !== $file->size) {
            $audioObject->contentSize((string) $file->size);
        }

        $duration = $this->isoDuration($file->duration);
        if (null !== $duration) {
            $audioObject->duration($duration);
        }

        return $audioObject;
    }

    private function video(Video $video, string $canonicalUrl): VideoObject
    {
        // Keyed on the YouTube id, not our row id: it's the permanent identifier the
        // source already assigns, and it survives a re-import.
        $videoId = $canonicalUrl . '#video-' . $video->youtubeId;
        $videoObject = $this->schemaOrg->getOrCreate(VideoObject::class, $videoId);
        $videoObject
            ->identifier($videoId)
            ->url($video->getYoutubeUrl())
            ->embedUrl('https://www.youtube.com/embed/' . $video->youtubeId);

        if ($this->hasText($video->title)) {
            $videoObject->name(trim($video->title));
        }

        if ($this->hasText($video->description)) {
            $videoObject->description(trim($video->description));
        }

        if ($this->hasText($video->thumbnailUrl)) {
            $videoObject->thumbnailUrl($video->thumbnailUrl);
        }

        if (null !== $video->date) {
            $videoObject->uploadDate($video->date->format('Y-m-d'));
        }

        return $videoObject;
    }

    private function person(string $siteUrl, string $name): Person
    {
        $name = trim($name);
        $personId = $siteUrl . '/people/' . $this->slug($name);

        $person = $this->schemaOrg->getOrCreate(Person::class, $personId);

        return $person
            ->identifier($personId)
            ->name($name);
    }

    private function organization(string $siteUrl, string $name): Organization
    {
        $name = trim($name);
        $organizationId = $siteUrl . '/publishers/' . $this->slug($name);

        $organization = $this->schemaOrg->getOrCreate(Organization::class, $organizationId);

        return $organization
            ->identifier($organizationId)
            ->name($name);
    }

    private function school(string $siteUrl, string $name): EducationalOrganization
    {
        $name = trim($name);
        $schoolId = $siteUrl . '/schools/' . $this->slug($name);

        $school = $this->schemaOrg->getOrCreate(EducationalOrganization::class, $schoolId);

        return $school
            ->identifier($schoolId)
            ->name($name);
    }

    /** Seconds → ISO 8601, the only duration format schema.org accepts. */
    private function isoDuration(?float $seconds): ?string
    {
        if (null === $seconds || $seconds <= 0.0) {
            return null;
        }

        $total = (int) round($seconds);

        return \sprintf('PT%dM%dS', intdiv($total, 60), $total % 60);
    }

    private function slug(string $value): string
    {
        return rawurlencode(mb_strtolower($value));
    }

    private function hasText(?string $value): bool
    {
        return null !== $value && '' !== trim($value);
    }
}
