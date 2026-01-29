<?php

namespace App\Workflow;

use App\Entity\Song;
use App\Workflow\SongWFDefinition as WF;
use Survos\StateBundle\Attribute\Place;
use Survos\StateBundle\Attribute\Transition;
use Survos\StateBundle\Attribute\Workflow;

#[Workflow(supports: [Song::class], name: self::WORKFLOW_NAME)]

class SongWFDefinition
{
	public const WORKFLOW_NAME = 'SongWorkflow';

	#[Place(initial: true)]
	public const PLACE_NEW = 'new';

	#[Place]
	public const PLACE_LYRICS = 'lyrics';

	#[Transition(from: [self::PLACE_NEW], to: self::PLACE_LYRICS)]
	public const TRANSITION_SYNC_LYRICS = 'sync_lyrics';
}
