<?php

namespace App\Workflow;

use App\Entity\Audio;
use App\Workflow\AudioWFDefinition as WF;
use Survos\StateBundle\Attribute\Place;
use Survos\StateBundle\Attribute\Transition;
use Survos\StateBundle\Attribute\Workflow;

#[Workflow(supports: [Audio::class], name: self::WORKFLOW_NAME)]

class AudioWFDefinition
{
	public const WORKFLOW_NAME = 'AudioWorkflow';

	#[Place(initial: true, next: [])]
	public const PLACE_NEW = 'new';

	#[Place(next: [])]
	public const PLACE_LYRICS = 'lyrics';

	#[Place(next: [])]
	public const PLACE_MIDI = 'midi';

    #[Place(next: [])]
    public const PLACE_XML = 'xml';

	#[Transition(from: [self::PLACE_NEW, self::PLACE_XML, self::PLACE_LYRICS], to: self::PLACE_LYRICS, async: true)]
	public const TRANSITION_EXTRACT_LYRICS = 'extract_lyrics';

	#[Transition(from: [self::PLACE_XML, self::PLACE_LYRICS], to: self::PLACE_MIDI, async: true)]
	public const TRANSITION_CREATE_MIDI = 'create_midi';

	#[Transition(from: [self::PLACE_MIDI, self::PLACE_XML], to: self::PLACE_XML, async: true)]
	public const TRANSITION_CREATE_XML = 'create_xml';
}
