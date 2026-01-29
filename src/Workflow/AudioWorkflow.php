<?php

namespace App\Workflow;

use App\Entity\Audio;
use App\Workflow\AudioWFDefinition as WF;
use Doctrine\ORM\EntityManagerInterface;
use Survos\StateBundle\Attribute\Workflow;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

class AudioWorkflow
{
	public const WORKFLOW_NAME = 'AudioWorkflow';

	public function __construct(
        private EntityManagerInterface $entityManager,
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
        // ~/g/whisper.cpp$ ./build/bin/whisper-cli -m models/ggml-base.en.bin -f ~/g/sites/kid-pan/test_sample/Hearthstone\ 2025/Everything\ is\ Temporary\ gv.mp3 ^C
        //
		$audio = $this->getAudio($event);
        $this->entityManager->flush();
	}


	#[AsTransitionListener(WF::WORKFLOW_NAME, WF::TRANSITION_CREATE_XML)]
	public function onCreateXml(TransitionEvent $event): void
	{
		$audio = $this->getAudio($event);
        $this->entityManager->flush();
	}
}
