<?php

namespace App\Controller;

use App\Entity\Song;
use App\Entity\Video;
use App\Form\SongType;
use App\Repository\SongRepository;
use Survos\ApiGrid\Service\MeiliService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/songs')]
class SongCollectionController extends AbstractController
{
    public function __construct()
    {

    }
    #[Route(path: '/browse/{apiRoute}', name: 'song_index', methods: ['GET'])]
    public function index(string $apiRoute=Song::MEILI_ROUTE) : Response
    {
//        $this->meiliService->getConfig()
        return $this->render('song/index.html.twig', [
                'apiRoute' => $apiRoute,
                'class' => Song::class,
        ]);
    }


}
