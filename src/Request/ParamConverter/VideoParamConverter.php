<?php

namespace App\Request\ParamConverter;

use App\Entity\Video;

use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

class VideoParamConverter implements ParamConverterInterface
{

    /**
     * @param ManagerRegistry $registry Manager registry
     */
    public function __construct(private readonly ?\Doctrine\Persistence\ManagerRegistry $registry = null)
    {
    }

    /**
     * {@inheritdoc}
     *
     * Check, if object supported by our converter
     */
    public function supports(ParamConverter $configuration): bool
    {
        return Video::class == $configuration->getClass();
    }

    /**
     * {@inheritdoc}
     *
     * Applies converting
     *
     * @throws \InvalidArgumentException When route attributes are missing
     * @throws NotFoundHttpException     When object not found
     * @throws Exception
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $params = $request->attributes->get('_route_params');

//        if (isset($params['videoId']) && ($videoId = $request->attributes->get('videoId')))

        $videoId = $request->attributes->get('videoId');
        if ($videoId === 'undefined') {
            throw new Exception("Invalid videoId " . $videoId);
        }

        // Check, if route attributes exists
        if (null === $videoId ) {
            if (!isset($params['videoId'])) {
                return; // no videoId in the route, so leave.  Could throw an exception.
            }
        }

        // Get actual entity manager for class.  We can also pass it in, but that won't work for the doctrine tree extension.
        $em = $this->registry->getManagerForClass($configuration->getClass());
        $repository = $em->getRepository($configuration->getClass());

        // Try to find video by its uniqueParameters.  Inspect the class to get this
        $video = $repository->findOneBy(['youtubeId' => $videoId]);

        if (null === $video || !($video instanceof Video)) {
            throw new NotFoundHttpException(sprintf('%s %s object not found.', $videoId, $configuration->getClass()));
        }

        // Map found video to the route's parameter
        $request->attributes->set($configuration->getName(), $video);
    }

}
