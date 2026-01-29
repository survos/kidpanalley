<?php

namespace App\Workflow;

use App\Entity\FileAsset;
use App\Workflow\FileAssetWFDefinition as WF;
use Doctrine\ORM\EntityManagerInterface;
use Survos\StateBundle\Attribute\Workflow;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Process\Process;

class FileAssetWorkflow
{
	public const WORKFLOW_NAME = 'FileAssetWorkflow';

	public function __construct(
        private EntityManagerInterface $entityManager,
    )
	{
	}


	public function getFileAsset(\Symfony\Component\Workflow\Event\Event $event): FileAsset
	{
		/** @var FileAsset */ return $event->getSubject();
	}

	#[AsTransitionListener(WF::WORKFLOW_NAME, WF::TRANSITION_PROBE)]
	public function onProbe(TransitionEvent $event): void
	{
		$fileAsset = $this->getFileAsset($event);
		$fileAsset->probedData = $this->probeFileAsset($fileAsset);
		$fileAsset->duration = $fileAsset->probedData['summary']['duration'] ?? null;
        $this->entityManager->flush();

	}

	private function probeFileAsset(FileAsset $fileAsset): array
	{
		$probeData = [
			'probedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
		];

		if (!is_file($fileAsset->path) || !is_readable($fileAsset->path)) {
			$probeData['error'] = 'File is missing or not readable.';
			return $probeData;
		}

		if (!$this->isFfprobeAvailable()) {
			$probeData['error'] = 'ffprobe is not available.';
			return $probeData;
		}

		$process = new Process([
			'ffprobe',
			'-v',
			'error',
			'-print_format',
			'json',
			'-show_format',
			'-show_streams',
			$fileAsset->path,
		]);
		$process->run();

		if (!$process->isSuccessful()) {
			$probeData['error'] = 'ffprobe failed.';
			$probeData['stderr'] = trim($process->getErrorOutput());
			$probeData['exitCode'] = $process->getExitCode();
			return $probeData;
		}

		$decoded = json_decode($process->getOutput(), true);
		if (!is_array($decoded)) {
			$probeData['error'] = 'ffprobe output could not be decoded.';
			return $probeData;
		}

		$format = $decoded['format'] ?? [];
		$streams = $decoded['streams'] ?? [];
		$primaryStream = $this->selectPrimaryStream($streams);

		$probeData['format'] = $format;
		$probeData['streams'] = $streams;
		$probeData['summary'] = [
			'formatName' => $format['format_name'] ?? null,
			'formatLongName' => $format['format_long_name'] ?? null,
			'duration' => isset($format['duration']) ? (float) $format['duration'] : null,
			'bitRate' => isset($format['bit_rate']) ? (int) $format['bit_rate'] : null,
			'size' => isset($format['size']) ? (int) $format['size'] : null,
			'startTime' => isset($format['start_time']) ? (float) $format['start_time'] : null,
			'tags' => $format['tags'] ?? null,
			'primaryStream' => $primaryStream,
		];

		return $probeData;
	}

	private function selectPrimaryStream(array $streams): ?array
	{
		if (!$streams) {
			return null;
		}

		$preferredTypes = ['video', 'audio'];
		foreach ($preferredTypes as $type) {
			foreach ($streams as $stream) {
				if (($stream['codec_type'] ?? null) === $type) {
					return $this->summarizeStream($stream);
				}
			}
		}

		return $this->summarizeStream($streams[0]);
	}

	private function summarizeStream(array $stream): array
	{
		return [
			'codecType' => $stream['codec_type'] ?? null,
			'codecName' => $stream['codec_name'] ?? null,
			'codecLongName' => $stream['codec_long_name'] ?? null,
			'bitRate' => isset($stream['bit_rate']) ? (int) $stream['bit_rate'] : null,
			'width' => isset($stream['width']) ? (int) $stream['width'] : null,
			'height' => isset($stream['height']) ? (int) $stream['height'] : null,
			'channels' => isset($stream['channels']) ? (int) $stream['channels'] : null,
			'sampleRate' => isset($stream['sample_rate']) ? (int) $stream['sample_rate'] : null,
		];
	}

	private function isFfprobeAvailable(): bool
	{
		$process = new Process(['which', 'ffprobe']);
		$process->run();

		return $process->isSuccessful();
	}
}
