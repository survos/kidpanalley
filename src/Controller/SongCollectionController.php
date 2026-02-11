<?php

namespace App\Controller;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\Song;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/songs')]
class SongCollectionController extends AbstractController
{
    #[Route(path: '/browse/{apiRoute}', name: 'song_index', methods: ['GET'])]
    public function index(IriConverterInterface $iriConverter, string $apiRoute = Song::DOCTRINE_ROUTE) : Response
    {
        // Doctrine-first: compute the API Platform collection IRI explicitly.
        // Keep apiRoute parameter for backwards compatibility / toggling.
        if ($apiRoute === Song::MEILI_ROUTE) {
            // legacy: Meili endpoint (optional)
            $apiGetCollectionUrl = '/api/meili/Song';
        } else {
            $apiGetCollectionUrl = $iriConverter->getIriFromResource(
                Song::class,
                operation: new GetCollection(name: Song::DOCTRINE_ROUTE)
            );
        }

        return $this->render('song/index.html.twig', [
            'apiRoute' => $apiRoute,
            'apiGetCollectionUrl' => $apiGetCollectionUrl,
            'class' => Song::class,
        ]);
    }


}
