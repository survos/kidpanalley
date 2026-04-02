<?php

namespace App\Controller;

use App\Entity\Song;
use App\Entity\SongLyrics;
use App\Form\SongType;
use App\Repository\AudioRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function show(Song $song, AudioRepository $audioRepository, EntityManagerInterface $entityManager) : Response|array
    {
        $audios = $audioRepository->findBy(['song' => $song], ['id' => 'DESC']);
        $songLyrics = $entityManager->getRepository(SongLyrics::class)->findBy(['song' => $song], ['id' => 'DESC']);

        $relatedFiles = [];
        foreach ($audios as $audio) {
            if (isset($audio->fileAsset) && $audio->fileAsset !== null) {
                $relatedFiles[$audio->fileAsset->id] = $audio->fileAsset;
            }
        }
        foreach ($songLyrics as $link) {
            if (isset($link->fileAsset) && $link->fileAsset !== null) {
                $relatedFiles[$link->fileAsset->id] = $link->fileAsset;
            }
        }

        return [
            'song' => $song,
            'audios' => $audios,
            'songLyrics' => $songLyrics,
            'relatedFiles' => array_values($relatedFiles),
        ];
    }
}
