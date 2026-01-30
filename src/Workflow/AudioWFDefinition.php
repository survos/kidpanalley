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

	#[Place(initial: true)]
	public const PLACE_NEW = 'new';

	#[Place]
	public const PLACE_LYRICS = 'lyrics';

	#[Place]
	public const PLACE_XML = 'xml';

	#[Transition(from: [self::PLACE_NEW, self::PLACE_XML, self::PLACE_LYRICS], to: self::PLACE_LYRICS)]
	public const TRANSITION_EXTRACT_LYRICS = 'extract_lyrics';

	#[Transition(from: [self::PLACE_LYRICS, self::PLACE_XML], to: self::PLACE_XML)]
	public const TRANSITION_CREATE_XML = 'create_xml';
}
