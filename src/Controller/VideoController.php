<?php

namespace App\Controller;

use App\Entity\Video;
use App\Form\VideoType;
use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/video')]
class VideoController extends AbstractController
{
    public function __construct(private \Doctrine\Persistence\ManagerRegistry $managerRegistry)
    {
    }
    #[Route(path: '/', name: 'video_index', methods: ['GET'])]
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
            'class' => Video::class,
            'videos' => $videoRepository->findBy([], ['id' => 'DESC'], 30),
            'videoCount' => $videoRepository->count([])
        ]);
    }

    #[Route(path: '/new', name: 'video_new', methods: ['GET', 'POST'])]
    public function new(Request $request) : Response
    {
        $video = new Video();
        $form = $this->createForm(VideoType::class, $video);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->managerRegistry->getManager();
            $entityManager->persist($video);
            $entityManager->flush();

            return $this->redirectToRoute('video_index');
        }
        return $this->render('video/new.html.twig', [
            'video' => $video,
            'form' => $form->createView(),
        ]);
    }
    #[Route(path: '/{videoId}', name: 'video_show', methods: ['GET'], options: ['expose' => true])]
    #[IsGranted('ROLE_ADMIN')]
    public function show(Video $video) : Response
    {
        return $this->render('video/show.html.twig', [
            'video' => $video,
        ]);
    }
    #[Route(path: '/{videoId}/edit', name: 'video_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Video $video) : Response
    {
        $form = $this->createForm(VideoType::class, $video);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->managerRegistry->getManager()->flush();

            return $this->redirectToRoute('video_index');
        }
        return $this->render('video/edit.html.twig', [
            'video' => $video,
            'form' => $form->createView(),
        ]);
    }
    #[Route(path: '/{videoId}', name: 'video_delete', methods: ['DELETE'])]
    public function delete(Request $request, Video $video) : Response
    {
        if ($this->isCsrfTokenValid('delete'.$video->getId(), $request->request->get('_token'))) {
            $entityManager = $this->managerRegistry->getManager();
            $entityManager->remove($video);
            $entityManager->flush();
        }
        return $this->redirectToRoute('video_index');
    }
}
