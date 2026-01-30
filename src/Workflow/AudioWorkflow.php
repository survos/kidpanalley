<?php

namespace App\Workflow;

use App\Entity\Audio;
use App\Workflow\AudioWFDefinition as WF;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\StateBundle\Attribute\Workflow;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Process\Process;

class AudioWorkflow
{
	public const WORKFLOW_NAME = 'AudioWorkflow';

	public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        #[Autowire('%env(WHISPER_CLI)%')] private string $whisperCli,
        #[Autowire('%env(WHISPER_MODEL)%')] private string $whisperModel,
        #[Autowire('%env(WHISPER_OUTPUT_DIR)%')] private string $whisperOutputDir,
        #[Autowire('%env(BASIC_PITCH_BIN)%')] private string $basicPitchBin,
        #[Autowire('%env(MUSICXML_PYTHON)%')] private string $musicXmlPython,
        #[Autowire('%env(MUSICXML_SCRIPT)%')] private string $musicXmlScript,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    )
	{
	}


	public function getAudio(\Symfony\Component\Workflow\Event\Event $event): Audio
	{
		/** @var Audio */ return $event->getSubject();
	}


	#[AsTransitionListener(WF::WORKFLOW_NAME, WF::TRANSITION_EXTRACT_LYRICS)]
	public function onExtractLyrics(TransitionEvent $event): void
	{
		$audio = $this->getAudio($event);
		$audioPath = $this->resolvePath($audio->fileAsset->path ?? null);
		if (!$audioPath || !is_file($audioPath) || !is_readable($audioPath)) {
			return;
		}

		if (!$this->whisperCli || !is_file($this->whisperCli)) {
			return;
		}

		if (!$this->whisperModel || !is_file($this->whisperModel)) {
			return;
		}

		$outputDir = $this->resolveOutputDir();
		if (!is_dir($outputDir)) {
			if (!mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
				$this->logger->error('Failed to create whisper output dir', [
					'path' => $outputDir,
				]);
				return;
			}
		}
		if (!is_dir($outputDir) || !is_writable($outputDir)) {
			$this->logger->error('whisper output dir not writable', [
				'path' => $outputDir,
			]);
			return;
		}

		$baseName = sprintf('audio_%d', $audio->id ?? time());
		$outputBase = $outputDir . '/' . $baseName;

		$process = new Process([
			$this->whisperCli,
			'-m',
			$this->whisperModel,
			'-f',
			$audioPath,
			'-ng',
			'-oj',
			'-otxt',
			'-of',
			$outputBase,
		]);
		$process->setTimeout(null);
        $this->logger->info('whisper-cli command: ' . "\n" . $process->getCommandLine());
		$process->run();

		if (!$process->isSuccessful()) {
			$this->logger->error('whisper-cli failed', [
				'command' => $process->getCommandLine(),
				'error' => trim($process->getErrorOutput()),
				'exitCode' => $process->getExitCode(),
			]);
			return;
		}

		$lyricsPath = $outputBase . '.txt';
		if (is_file($lyricsPath)) {
			$audio->song->lyrics = trim((string) file_get_contents($lyricsPath));
		}
        $this->entityManager->flush();
	}


	#[AsTransitionListener(WF::WORKFLOW_NAME, WF::TRANSITION_CREATE_XML)]
	public function onCreateXml(TransitionEvent $event): void
	{
		$audio = $this->getAudio($event);
		$audioPath = $this->resolvePath($audio->fileAsset->path ?? null);
		if (!$audioPath || !is_file($audioPath) || !is_readable($audioPath)) {
			return;
		}

		if (!$this->basicPitchBin || !is_file($this->basicPitchBin)) {
			return;
		}

		if (!$this->musicXmlPython || !is_file($this->musicXmlPython)) {
			return;
		}

		if (!$this->musicXmlScript || !is_file($this->musicXmlScript)) {
			return;
		}

		$outputDir = $this->resolveOutputDir();
		if (!is_dir($outputDir)) {
			if (!mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
				$this->logger->error('Failed to create whisper output dir', [
					'path' => $outputDir,
				]);
				return;
			}
		}
		if (!is_dir($outputDir) || !is_writable($outputDir)) {
			$this->logger->error('whisper output dir not writable', [
				'path' => $outputDir,
			]);
			return;
		}

		$baseName = sprintf('audio_%d', $audio->id ?? time());
		$whisperJson = $outputDir . '/' . $baseName . '.json';
		if (!is_file($whisperJson)) {
			return;
		}

		$basicPitchProcess = new Process([
			$this->basicPitchBin,
			$outputDir,
			$audioPath,
		]);
        $this->logger->info('command: ' . "\n" . $basicPitchProcess->getCommandLine());

		$basicPitchProcess->setTimeout(null);
		$basicPitchProcess->run();
		$basicPitchStdout = trim($basicPitchProcess->getOutput());
		$basicPitchStderr = trim($basicPitchProcess->getErrorOutput());
		if ($basicPitchStdout !== '') {
			$this->logger->info('basic-pitch output', ['stdout' => $basicPitchStdout]);
		}
		if ($basicPitchStderr !== '') {
			$this->logger->warning('basic-pitch stderr', ['stderr' => $basicPitchStderr]);
		}
		if (!$basicPitchProcess->isSuccessful()) {
			$this->logger->error('basic-pitch failed', [
				'command' => $basicPitchProcess->getCommandLine(),
				'error' => $basicPitchStderr,
				'exitCode' => $basicPitchProcess->getExitCode(),
			]);
			return;
		}

		$midiPath = $outputDir . '/' . pathinfo($audioPath, PATHINFO_FILENAME) . '.mid';
		if (!is_file($midiPath)) {
			$altMidiPath = $outputDir . '/' . pathinfo($audioPath, PATHINFO_FILENAME) . '_basic_pitch.mid';
			if (is_file($altMidiPath)) {
				$midiPath = $altMidiPath;
			}
		}
		if (!is_file($midiPath)) {
			$this->logger->error('basic-pitch did not produce midi file', [
				'expected' => $midiPath,
				'outputDir' => $outputDir,
			]);
			return;
		}

		$musicXmlPath = $outputDir . '/' . $baseName . '.musicxml';
		$musicXmlProcess = new Process([
			$this->musicXmlPython,
			$this->musicXmlScript,
			'--midi',
			$midiPath,
			'--whisper-json',
			$whisperJson,
			'--output',
			$musicXmlPath,
			'--title',
			$audio->song->title,
			'--creator',
			$audio->fileAsset->probedData['summary']['tags']['artist']
				?? $audio->fileAsset->probedData['summary']['tags']['composer']
				?? '',
			'--simplify',
			'--grid',
			'1.0',
			'--melody',
		]);
        $this->logger->info('command: ' . "\n" . $musicXmlProcess->getCommandLine());
		$musicXmlProcess->setTimeout(null);
		$musicXmlProcess->run();
		$musicXmlStdout = trim($musicXmlProcess->getOutput());
		$musicXmlStderr = trim($musicXmlProcess->getErrorOutput());
		if ($musicXmlStdout !== '') {
			$this->logger->info('musicxml output', ['stdout' => $musicXmlStdout]);
		}
		if ($musicXmlStderr !== '') {
			$this->logger->warning('musicxml stderr', ['stderr' => $musicXmlStderr]);
		}
		if (!$musicXmlProcess->isSuccessful()) {
			$this->logger->error('musicxml build failed', [
				'command' => $musicXmlProcess->getCommandLine(),
				'error' => $musicXmlStderr,
				'exitCode' => $musicXmlProcess->getExitCode(),
			]);
			return;
		}
		if (!is_file($musicXmlPath)) {
			$this->logger->error('musicxml file missing after build', [
				'path' => $musicXmlPath,
			]);
			return;
		}
        $this->logger->warning($musicXmlPath);
        $this->entityManager->flush();
	}

	private function resolvePath(?string $path): ?string
	{
		if (!$path) {
			return null;
		}

		if (str_starts_with($path, '/')) {
			return $path;
		}

		$absolute = $this->projectDir . '/' . ltrim($path, '/');
		$resolved = realpath($absolute);
		return $resolved ?: $absolute;
	}

	private function resolveOutputDir(): string
	{
		$dir = $this->whisperOutputDir ?: $this->projectDir . '/var/whisper';
		if (!str_starts_with($dir, '/')) {
			$dir = $this->projectDir . '/' . ltrim($dir, '/');
		}
		return $dir;
	}
}
