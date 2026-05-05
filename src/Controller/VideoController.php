<?php

namespace App\Controller;

use App\Entity\Video;
use App\Repository\VideoRepository;
use Survos\FieldBundle\Attribute\ControllerMeta;
use Survos\FieldBundle\Attribute\RouteMeta;
use Survos\FieldBundle\Enum\Audience;
use Survos\FieldBundle\Enum\Purpose;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/video')]
#[ControllerMeta(entity: Video::class, audience: Audience::Public, tags: ['catalog', 'media'])]
class VideoController extends AbstractController
{
    public function __construct(private \Doctrine\Persistence\ManagerRegistry $managerRegistry)
    {
    }
    #[Route(path: '/index', name: 'video_index', methods: ['GET'])]
    #[RouteMeta(
        description: 'Legacy server-rendered index of YouTube videos.',
        purpose: Purpose::Custom,
        sitemap: true,
        changefreq: 'weekly',
        priority: 0.6,
        tags: ['legacy']
    )]
    public function index(VideoRepository $videoRepository) : Response
    {
        return $this->render('video/index.html.twig', [
            'videos' => $videoRepository->findBy([], ['id' => 'DESC']),
            'videoCount' => $videoRepository->count([])
        ]);
    }
    #[Route(path: '/browse', name: 'video_browse', methods: ['GET'])]
    #[RouteMeta(
        description: 'Browse and search YouTube videos through the Doctrine-backed API grid.',
        purpose: Purpose::List,
        sitemap: true,
        changefreq: 'weekly',
        priority: 0.8
    )]
    public function browse(
        VideoRepository $videoRepository) : Response
    {
        return $this->render('video/browse.html.twig', [
            'class' => Video::class,
            'videos' => $videoRepository->findBy([], ['id' => 'DESC'], 30),
            'videoCount' => $videoRepository->count([])
        ]);
    }

    #[Route(path: '/{videoId}', name: 'video_show', methods: ['GET'], options: ['expose' => true])]
    #[RouteMeta(
        description: 'Video detail page for a single YouTube video and its linked song.',
        purpose: Purpose::Show,
        sitemap: true,
        changefreq: 'monthly',
        priority: 0.7
    )]
    public function show(Video $video) : Response
    {
        return $this->render('video/show.html.twig', [
            'video' => $video,
        ]);
    }
}
