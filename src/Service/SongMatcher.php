<?php

namespace App\Service;

use App\Entity\Song;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Canonical song title matching and Song record lookup / creation.
 *
 * Deduplication strategy:
 *   1. Normalize the raw filename/title (strip track numbers, parentheticals, known suffixes).
 *   2. Exact case-insensitive lookup against existing Song titles.
 *   3. Levenshtein scan against all known normalized titles — merge if within threshold.
 *   4. If no match found, create a new Song with a full-slug code.
 *
 * Song identity is title-only (cross-school). School+year live on Audio/FileAsset rows.
 */
class SongMatcher
{
    /**
     * Known file-name suffixes that do NOT affect the canonical title.
     * Ordered longest-first so compound suffixes match before their substrings.
     */
    private const STRIP_SUFFIXES = [
        'gtr only', 'guitar only', 'guide vocal', 'guide vox',
        'backing track', 'ai version', 'ver \d+',
        'guitar', 'gtr', 'gv', 'wf', 'wfly',
        'instrumental', 'inst',
        'demo', 'draft', 'rough', 'mix',
        'drums', 'bass', 'keys',
        'arr', 'arrangement',
        'cover', 'remix',
        'concert', 'live',
        'new', 'edit', 'final',
        'v\d+',
        '[A-G](?:#|b)?\s*(?:maj|min|minor|major)?',
        '\d{2,3}\s*bpm',
        'ai',
        'bt',
        'bt2',
    ];

    /**
     * Levenshtein edit-distance threshold as a fraction of the shorter title's length.
     * 0.15 → allow up to 15% of characters to differ.
     * Minimum absolute threshold: 2 (handles 1-char typos in short titles safely).
     */
    private const LEVENSHTEIN_RATIO = 0.15;
    private const LEVENSHTEIN_MIN   = 2;

    /**
     * In-memory cache: normalized title → Song.
     * Built lazily and invalidated on flush.
     * @var array<string, Song>
     */
    private array $cache = [];

    /** Whether the cache has been populated from the DB. */
    private bool $cacheLoaded = false;

    public function __construct(
        private readonly SongRepository $songRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Normalize a raw filename/title into a canonical song title.
     * This is the same logic used for matching — call it before persisting
     * a title so the stored value is already canonical.
     */
    public function normalizeTitle(string $raw): string
    {
        $base = pathinfo($raw, PATHINFO_FILENAME);

        // Strip leading track numbers: "03. ", "3 - ", "03-"
        $base = (string) preg_replace('/^\d+[\.\-\s]+/', '', $base);

        // Strip parentheticals: "(live)", "(feat. X)", "(2019)"
        $base = (string) preg_replace('/\s*\([^)]*\)\s*/', ' ', $base);

        // Normalize separators to space
        $base = str_replace(['_', '-'], ' ', $base);

        // Strip known trailing suffixes (case-insensitive, repeated)
        $suffixPattern = implode('|', self::STRIP_SUFFIXES);
        do {
            $prev = $base;
            $base = (string) preg_replace(
                '/[\s\-_]+(?:' . $suffixPattern . ')[\s\-_]*$/iu',
                '',
                $base
            );
        } while ($base !== $prev);

        // Collapse whitespace
        $base = (string) preg_replace('/\s{2,}/', ' ', $base);

        return trim($base);
    }

    /**
     * Find an existing Song by exact or fuzzy title match, or create a new one.
     *
     * @param string      $rawTitle  Raw filename or title string.
     * @param string|null $school    School name (stored on Audio/FileAsset, used for code generation only).
     * @param int|null    $year      Year (same — code generation only).
     * @param bool        $persist   Whether to persist a newly created Song immediately.
     */
    public function findOrCreate(
        string $rawTitle,
        ?string $school = null,
        ?int $year = null,
        bool $persist = true,
    ): Song {
        $canonical = $this->normalizeTitle($rawTitle);

        // 1. Exact normalized match (cache or DB)
        $song = $this->findByNormalized($canonical);
        if ($song !== null) {
            return $song;
        }

        // 2. Levenshtein scan
        $song = $this->findByLevenshtein($canonical);
        if ($song !== null) {
            // Cache under the new variant so future lookups are O(1)
            $this->cache[mb_strtolower($canonical)] = $song;
            return $song;
        }

        // 3. Create new Song
        $code = $this->buildCode($canonical, $school, $year);
        $song = new Song($code);
        $song->title = $canonical;

        if ($persist) {
            $this->entityManager->persist($song);
        }

        $this->cache[mb_strtolower($canonical)] = $song;

        return $song;
    }

    /**
     * Warm the in-memory cache from all Song records already in the DB.
     * Call this once before a bulk import to avoid per-row SELECT queries.
     */
    public function warmCache(): void
    {
        if ($this->cacheLoaded) {
            return;
        }

        foreach ($this->songRepository->findAll() as $song) {
            if ($song->title !== null) {
                $this->cache[mb_strtolower($this->normalizeTitle($song->title))] = $song;
            }
        }

        $this->cacheLoaded = true;
    }

    /**
     * Clear the in-memory cache (call after EntityManager::clear() in batch loops).
     */
    public function clearCache(): void
    {
        $this->cache      = [];
        $this->cacheLoaded = false;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function findByNormalized(string $canonical): ?Song
    {
        $key = mb_strtolower($canonical);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        // Fallback to DB (for first use before warmCache())
        $song = $this->songRepository->findOneByTitleNormalized($canonical);
        if ($song !== null) {
            $this->cache[$key] = $song;
        }

        return $song;
    }

    private function findByLevenshtein(string $canonical): ?Song
    {
        $needle = mb_strtolower($canonical);
        $needleLen = mb_strlen($needle);
        $threshold = max(self::LEVENSHTEIN_MIN, (int) ceil($needleLen * self::LEVENSHTEIN_RATIO));

        $bestSong     = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($this->cache as $key => $song) {
            // Skip if length difference alone exceeds threshold (fast bail-out)
            if (abs(mb_strlen($key) - $needleLen) > $threshold) {
                continue;
            }

            // levenshtein() only works on byte-strings; for ASCII titles this is fine.
            // For multibyte, fall back to a simple mb_substr distance approximation.
            $distance = levenshtein($needle, $key);

            if ($distance <= $threshold && $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestSong     = $song;
            }
        }

        return $bestSong;
    }

    /**
     * Build a stable, collision-resistant code from the full title slug.
     * Format: {school4}-{year}-{title-slug} truncated to 64 chars.
     */
    public function buildCode(string $title, ?string $school, ?int $year): string
    {
        $slug = mb_strtolower($title);
        $slug = (string) preg_replace('/\s+/', '-', $slug);
        $slug = (string) preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = trim($slug, '-');

        $schoolPart = $school
            ? mb_substr(mb_strtolower((string) preg_replace('/[^a-z0-9]/i', '', $school)), 0, 4)
            : 'ns';
        $yearPart = $year ? (string) $year : '0';

        return mb_substr(sprintf('%s-%s-%s', $schoolPart, $yearPart, $slug), 0, 64);
    }
}
