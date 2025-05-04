<?php

namespace App\Controller;

use App\Entity\Song;
use App\Form\SongType;
use App\Repository\SongRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/song/{songId}', priority: 10000)]
class SongController extends AbstractController
{
    public function __construct(private readonly \Doctrine\Persistence\ManagerRegistry $managerRegistry)
    {

    }

    #[Route('/', name: 'song_show', options: ['expose' => true], methods: ['GET'])]
    public function show(Song $song) : Response
    {
//        return new Response("<html><body></body></html>");
        return $this->render('song/show.html.twig', [
            'song' => $song,
        ]);
    }
}
