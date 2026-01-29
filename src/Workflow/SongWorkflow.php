<?php

namespace App\Workflow;

use App\Entity\Song;
use App\Services\DocumentTextExtractor;
use App\Workflow\SongWFDefinition as WF;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

class SongWorkflow
{
	private array $lyricsCache = [];

	public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentTextExtractor $documentTextExtractor,
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    )
	{
	}

	public function getSong(\Symfony\Component\Workflow\Event\Event $event): Song
	{
		/** @var Song */ return $event->getSubject();
	}

	#[AsGuardListener(WF::WORKFLOW_NAME, WF::TRANSITION_SYNC_LYRICS)]
	public function onSyncLyricsGuard(GuardEvent $event): void
	{
		$song = $this->getSong($event);
		$lyrics = $this->findLyricsForSong($song);
		if ($lyrics === null) {
			$event->setBlocked(true, 'Lyrics not found for song title.');
			return;
		}

		if ($song->id !== null) {
			$this->lyricsCache[$song->id] = $lyrics;
		}
	}

	#[AsTransitionListener(WF::WORKFLOW_NAME, WF::TRANSITION_SYNC_LYRICS)]
	public function onSyncLyrics(TransitionEvent $event): void
	{
		$song = $this->getSong($event);
		$lyrics = null;
		if ($song->id !== null && array_key_exists($song->id, $this->lyricsCache)) {
			$lyrics = $this->lyricsCache[$song->id];
			unset($this->lyricsCache[$song->id]);
		}

		$lyrics ??= $this->findLyricsForSong($song);
		if ($lyrics === null) {
			return;
		}

		$song->lyrics = $lyrics;
		$this->entityManager->flush();
	}

	private function findLyricsForSong(Song $song): ?string
	{
		if (!$song->title) {
			return null;
		}

		$lyricsDir = $this->projectDir . '/data/lyrics';
		if (!is_dir($lyricsDir)) {
			$this->logger->warning('Lyrics directory missing', ['path' => $lyricsDir]);
			return null;
		}

		$targetTitle = $this->normalizeTitle($song->title);
		$finder = new Finder();
		$finder->files()->in($lyricsDir)->name('*.doc*');

		foreach ($finder as $file) {
			$filePath = $file->getRealPath();
			if (!$filePath || !is_readable($filePath)) {
				continue;
			}

			$extension = strtolower($file->getExtension());
			if ($extension === 'doc') {
				try {
					$text = $this->documentTextExtractor->extractFromDoc($filePath);
				} catch (\Throwable $e) {
					$this->logger->warning('Failed to extract .doc lyrics', [
						'file' => $filePath,
						'error' => $e->getMessage(),
					]);
					continue;
				}
				$songData = $this->matchTitleInText($text, $targetTitle);
				if ($songData !== null) {
					return $songData['lyrics'];
				}
				continue;
			}

			try {
				$text = $this->documentTextExtractor->extractFromDocx($filePath);
			} catch (\Throwable $e) {
				$this->logger->warning('Failed to extract .docx lyrics', [
					'file' => $filePath,
					'error' => $e->getMessage(),
				]);
				continue;
			}
			$songData = $this->parseSongText($text);
			if ($songData && $this->normalizeTitle($songData['title']) === $targetTitle) {
				return $songData['lyrics'];
			}
		}

		return null;
	}

	private function matchTitleInText(string $text, string $targetTitle): ?array
	{
		$chunks = preg_split("/\f/u", $text) ?: [];
		foreach ($chunks as $chunk) {
			$songData = $this->parseSongText($chunk);
			if ($songData && $this->normalizeTitle($songData['title']) === $targetTitle) {
				return $songData;
			}
		}

		return null;
	}

	private function parseSongText(string $text): ?array
	{
		$text = str_replace(["\r\n", "\r"], "\n", $text);
		$lines = preg_split("/\n/u", $text) ?: [];

		$lineCount = count($lines);
		$title = null;
		$startIndex = 0;
		for ($i = 0; $i < $lineCount; $i++) {
			$line = trim($lines[$i]);
			if ($line === '') {
				continue;
			}
			$title = $line;
			$startIndex = $i + 1;
			break;
		}

		if ($title === null) {
			return null;
		}

		if ($startIndex < $lineCount && stripos(trim($lines[$startIndex]), 'by ') === 0) {
			$startIndex++;
		}

		$lyricsLines = array_slice($lines, $startIndex);
		while ($lyricsLines && trim($lyricsLines[0]) === '') {
			array_shift($lyricsLines);
		}
		while ($lyricsLines && trim($lyricsLines[count($lyricsLines) - 1]) === '') {
			array_pop($lyricsLines);
		}

		return [
			'title' => $title,
			'lyrics' => implode("\n", $lyricsLines),
		];
	}

	private function normalizeTitle(string $title): string
	{
		$normalized = preg_replace('/\s+/u', ' ', trim($title)) ?? '';
		return mb_strtolower($normalized);
	}
}
