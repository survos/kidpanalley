<?php

namespace App\Controller;

use App\Entity\Song;
use App\Entity\SongLyrics;
use App\Form\SongType;
use App\Repository\AudioRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Survos\FieldBundle\Attribute\ControllerMeta;
use Survos\FieldBundle\Attribute\RouteMeta;
use Survos\FieldBundle\Enum\Audience;
use Survos\FieldBundle\Enum\Purpose;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/song/{songId}', priority: 10000)]
#[ControllerMeta(entity: Song::class, audience: Audience::Public, tags: ['catalog'])]
//#[Route(path: '/song/{id:song}', priority: 10000)]
class SongController extends AbstractController
{
    public function __construct()
    {

    }

    #[Route('/', name: 'song_show', options: ['expose' => true], methods: [Request::METHOD_GET])]
    #[Template('song/show.html.twig')]
    #[RouteMeta(
        description: 'Song detail page with lyrics, audio recordings, source files, and linked media.',
        purpose: Purpose::Show,
        sitemap: true,
        changefreq: 'monthly',
        priority: 0.7
    )]
    public function show(
        Song $song,
        AudioRepository $audioRepository,
        EntityManagerInterface $entityManager,
    ): Response|array {
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

        $notesData = [];
        if ($song->notes !== null && $song->notes !== '') {
            $decoded = json_decode((string) $song->notes, true);
            if (is_array($decoded)) {
                $notesData = $decoded;
            }
        }

        return [
            'song' => $song,
            'audios' => $audios,
            'songLyrics' => $songLyrics,
            'relatedFiles' => array_values($relatedFiles),
            'notesData' => $notesData,
        ];
    }
}
