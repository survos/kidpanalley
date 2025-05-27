<?php

namespace App\Controller;

use App\Entity\Song;
use App\Form\SongType;
use App\Repository\SongRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/song/{songId}', priority: 10000)]
//#[Route(path: '/song/{id:song}', priority: 10000)]
class SongController extends AbstractController
{
    public function __construct()
    {

    }

    #[Route('/', name: 'song_show', options: ['expose' => true], methods: [Request::METHOD_GET])]
    #[Template('song/show.html.twig')]
    public function show(Song $song) : Response|array
    {
        return ['song' => $song];
    }
}
