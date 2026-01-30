<?php

namespace App\Controller;

use App\Entity\Audio;
use App\Repository\AudioRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route(path: '/audio', priority: 10000)]
class AudioController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }
    #[Route('/', name: 'audio_index', methods: [Request::METHOD_GET])]
    #[Template('audio/index.html.twig')]
    public function index(AudioRepository $audioRepository): Response|array
    {
        return [
            'audios' => $audioRepository->findBy([], ['id' => 'DESC'], 25),
        ];
    }

    #[Route('/{id}', name: 'audio_show', options: ['expose' => true], methods: [Request::METHOD_GET])]
    #[Template('audio/show.html.twig')]
    public function show(Audio $audio): Response|array
    {
        return [
            'audio' => $audio,
        ];
    }

    #[Route('/{id}/musicxml', name: 'audio_musicxml', methods: [Request::METHOD_GET])]
    public function musicXml(Audio $audio): Response
    {
        if (!$audio->musicXml) {
            return new Response('MusicXML not available', Response::HTTP_NOT_FOUND);
        }

        return new Response(
            $audio->musicXml,
            Response::HTTP_OK,
            ['Content-Type' => 'application/vnd.recordare.musicxml+xml; charset=utf-8']
        );
    }

    #[Route('/{id}/file', name: 'audio_file', methods: [Request::METHOD_GET])]
    public function audioFile(Audio $audio): Response
    {
        $path = $this->resolvePath($audio->fileAsset->path ?? null);
        if (!$path || !is_file($path)) {
            return new Response('Audio file not available', Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($path);
    }

    private function resolvePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        $absolute = $this->projectDir . '/' . ltrim($path, '/');
        $resolved = realpath($absolute);
        return $resolved ?: $absolute;
    }
}
