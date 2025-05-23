<?php

namespace App\Controller;

use App\Entity\Video;
use App\Form\VideoType;
use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/video')]
class VideoController extends AbstractController
{
    public function __construct(private \Doctrine\Persistence\ManagerRegistry $managerRegistry)
    {
    }
    #[Route(path: '/index', name: 'video_index', methods: ['GET'])]
    public function index(VideoRepository $videoRepository) : Response
    {
        return $this->render('video/index.html.twig', [
            'videos' => $videoRepository->findBy([], ['id' => 'DESC']),
            'videoCount' => $videoRepository->count([])
        ]);
    }
    #[Route(path: '/browse', name: 'video_browse', methods: ['GET'])]
    public function browse(VideoRepository $videoRepository) : Response
    {
        return $this->render('video/browse.html.twig', [
            'apiRoute' => Video::MEILI_ROUTE,
            'class' => Video::class,
            'videos' => $videoRepository->findBy([], ['id' => 'DESC'], 30),
            'videoCount' => $videoRepository->count([])
        ]);
    }

    #[Route(path: '/{videoId}', name: 'video_show', methods: ['GET'], options: ['expose' => true])]
    public function show(Video $video) : Response
    {
        return $this->render('video/show.html.twig', [
            'video' => $video,
        ]);
    }
}
