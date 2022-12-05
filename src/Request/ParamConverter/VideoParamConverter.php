<?php

namespace App\Request\ParamConverter;

use App\Entity\Video;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class VideoParamConverter implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        // get the argument type (e.g. BookingId)
        $argumentType = $argument->getType();
        switch ($argumentType) {
            case Video::class:
                $repository = $this->entityManager->getRepository($argumentType);
                $value = $request->attributes->get('videoId');
                if (!is_string($value)) {
                    return [];
                }
                // Try to find video by its uniqueParameters.  Inspect the class to get this
                return [$repository->findOneBy(['youtubeId' => $value])];

        }

        return [];
    }

    public function __construct(
        private EntityManagerInterface $entityManager
    )
    {
    }


}
