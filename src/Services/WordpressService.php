<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Song;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Survos\WordpressBundle\Client\WordpressClientInterface;
use Survos\WordpressBundle\Exception\WordpressExceptionInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Publishes a Song as a page on the KPA WordPress site, through survos/wordpress-bundle.
 *
 * Replaces the hand-rolled integration in AppController (survos-sites/ff#5): a `curl` command
 * assembled by sprintf and run through exec(), a second half-written HttpClient path after an
 * unconditional return, and credentials read out of container parameters. All of that is now the
 * bundle's job.
 *
 * Note that publishing is a console command, not a controller action: it writes to a live public
 * website, which is not something a GET request should do.
 */
final class WordpressService
{
    public function __construct(
        private readonly WordpressClientInterface $wordpress,
        private readonly SongRepository $songRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * The wp/v2/pages payload for a song. Includes `id` when the song already has a page, which is
     * what makes save() an update rather than a second page.
     *
     * @return array<string, mixed>
     */
    public function pagePayload(Song $song): array
    {
        $payload = [
            'title' => (string) $song->title,
            'slug' => (new AsciiSlugger())->slug(strtolower((string) $song->title))->toString(),
            'status' => 'draft',
            'content' => $this->content($song),
        ];

        if (null !== $song->wordpressPageId) {
            $payload['id'] = $song->wordpressPageId;
        }

        return $payload;
    }

    /**
     * Creates or updates the song's page and records the resulting id.
     *
     * @return array<string, mixed> the page as WordPress returned it
     */
    public function publish(Song $song): array
    {
        $page = $this->wordpress->pages()->save($this->pagePayload($song));

        if (isset($page['id'])) {
            $song->wordpressPageId = (int) $page['id'];
            $this->entityManager->flush();
        }

        return $page;
    }

    #[AsCommand('kpa:song:publish', 'publish a song as a page on the KPA WordPress site')]
    public function publishCommand(
        SymfonyStyle $io,
        #[Argument('song id')] string $songId,
        #[Option('actually write to WordPress; without it the payload is only printed')] bool $publish = false,
    ): int {
        $song = $this->songRepository->find($songId);
        if (null === $song) {
            $io->error(sprintf('No song with id "%s".', $songId));

            return Command::FAILURE;
        }

        $payload = $this->pagePayload($song);
        $io->definitionList(
            ['song' => $songId],
            ['title' => $payload['title']],
            ['slug' => $payload['slug']],
            ['page id' => $song->wordpressPageId ?? '(none yet — will create)'],
            ['content' => substr((string) $payload['content'], 0, 200).'…'],
        );

        if (!$publish) {
            $io->note('Preview only. Re-run with --publish to write this to '.$this->wordpress->siteUrl().'.');

            return Command::SUCCESS;
        }

        if (!$this->wordpress->isAuthenticated()) {
            $io->error('No credentials configured — set API_USERNAME and API_PASSWORD (an Application Password) in .env.local.');

            return Command::FAILURE;
        }

        try {
            $page = $this->publish($song);
        } catch (WordpressExceptionInterface $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Page #%s: %s', $page['id'] ?? '?', $page['link'] ?? $this->wordpress->siteUrl()));

        return Command::SUCCESS;
    }

    /** Lyrics plus the credits that belong with them; kept deliberately plain so WordPress owns the styling. */
    private function content(Song $song): string
    {
        $parts = [];

        if (null !== $song->lyrics && '' !== trim($song->lyrics)) {
            $parts[] = nl2br(htmlspecialchars(trim($song->lyrics), \ENT_QUOTES | \ENT_SUBSTITUTE));
        }

        foreach (['Written by' => $song->writers, 'School' => $song->school, 'Featured artist' => $song->featuredArtist] as $label => $value) {
            if (null !== $value && '' !== trim($value)) {
                $parts[] = sprintf('<p><strong>%s:</strong> %s</p>', $label, htmlspecialchars(trim($value), \ENT_QUOTES | \ENT_SUBSTITUTE));
            }
        }

        return implode("\n\n", $parts);
    }
}
