<?php

namespace App\Controller;

use App\Entity\Song;
use Survos\FieldBundle\Attribute\ControllerMeta;
use Survos\FieldBundle\Attribute\RouteMeta;
use Survos\FieldBundle\Enum\Audience;
use Survos\FieldBundle\Enum\Purpose;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/songs')]
#[ControllerMeta(entity: Song::class, audience: Audience::Public, tags: ['catalog', 'browse'])]
class SongListController extends AbstractController
{
    #[Route(path: '/browse', name: 'song_index', methods: ['GET'])]
    #[RouteMeta(
        description: 'Browse and search the song catalog through the Doctrine-backed API grid.',
        purpose: Purpose::List,
        sitemap: true,
        changefreq: 'weekly',
        priority: 0.8
    )]
    public function index() : Response
    {
        return $this->render('song/index.html.twig', [
            'class' => Song::class,
        ]);
    }


}
