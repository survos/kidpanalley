<?php

namespace App\Command;

use App\Entity\FileAsset;
use App\Entity\SongLyrics;
use App\Service\SongMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:link-lyrics', 'Create SongLyrics links from FileAsset.lyricsCandidates')]
final class LinkLyricsFromFileAssetsCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SongMatcher $songMatcher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('clear existing SongLyrics first')]
        bool $reset = false,
        #[Option('max lyrics file assets to process')]
        ?int $limit = null,
    ): int {
        if ($reset) {
            $this->entityManager->createQuery('DELETE FROM App\\Entity\\SongLyrics sl')->execute();
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fa')
            ->from(FileAsset::class, 'fa')
            ->where('fa.type = :type')
            ->andWhere('fa.lyricsCandidates IS NOT NULL')
            ->setParameter('type', 'lyrics')
            ->orderBy('fa.id', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        $assets = $qb->getQuery()->getResult();
        $songLyricsRepo = $this->entityManager->getRepository(SongLyrics::class);

        $this->songMatcher->warmCache();

        $created = 0;
        $updated = 0;
        $files = 0;
        $seenPairs = [];

        foreach ($assets as $fileAsset) {
            $files++;
            foreach ((array) $fileAsset->lyricsCandidates as $rawTitle => $lyrics) {
                $variants = is_array($lyrics) ? $lyrics : [$lyrics];
                foreach ($variants as $lyricsText) {
                    $lyricsText = trim((string) $lyricsText);
                    if ($lyricsText === '') {
                        continue;
                    }

                    $song = $this->songMatcher->findOrCreate(
                        $rawTitle,
                        $fileAsset->school,
                        $fileAsset->year,
                        persist: true,
                    );

                    $pairKey = ($song->id ?? ('new' . spl_object_id($song))) . ':' . $fileAsset->id;
                    if (isset($seenPairs[$pairKey])) {
                        continue;
                    }

                    $existing = $songLyricsRepo->findOneBy(['song' => $song, 'fileAsset' => $fileAsset]);
                    if ($existing instanceof SongLyrics) {
                        if ($existing->lyricsText === null || mb_strlen($lyricsText) > mb_strlen((string) $existing->lyricsText)) {
                            $existing->lyricsText = $lyricsText;
                            $existing->sourceTitle = mb_substr($rawTitle, 0, 255);
                            $updated++;
                        }
                        $seenPairs[$pairKey] = true;
                    } else {
                        $this->entityManager->persist(new SongLyrics(
                            song: $song,
                            fileAsset: $fileAsset,
                            lyricsText: $lyricsText,
                            sourceTitle: mb_substr($rawTitle, 0, 255),
                        ));
                        $created++;
                        $seenPairs[$pairKey] = true;
                    }

                    $existingLyrics = isset($song->lyrics) ? (string) $song->lyrics : '';
                    if ($existingLyrics === '' || mb_strlen($lyricsText) > mb_strlen($existingLyrics)) {
                        $song->lyrics = $lyricsText;
                    }
                }
            }

            if ($files % 200 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $this->songMatcher->clearCache();
                $this->songMatcher->warmCache();
                $songLyricsRepo = $this->entityManager->getRepository(SongLyrics::class);
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Processed %d lyrics files, created %d song-file links, updated %d existing links',
            $files,
            $created,
            $updated,
        ));

        return Command::SUCCESS;
    }
}
