<?php

namespace App\Workflow;

use App\Entity\FileAsset;
use Survos\StateBundle\Attribute\Place;
use Survos\StateBundle\Attribute\Transition;
use Survos\StateBundle\Attribute\Workflow;

#[Workflow(supports: [FileAsset::class], name: self::WORKFLOW_NAME)]
class FileAssetWFDefinition
{
	public const WORKFLOW_NAME = 'FileAssetWorkflow';

	#[Place(initial: true)]
	public const PLACE_NEW = 'new';

	#[Place]
	public const PLACE_PROBED = 'probed';

	#[Transition(from: [self::PLACE_NEW], to: self::PLACE_PROBED)]
	public const TRANSITION_PROBE = 'probe';
}
