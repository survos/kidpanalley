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

    #[Route(path: '/', name: 'song_show', methods: ['GET'], options: ['expose' => true])]
    public function show(Song $song) : Response
    {
//        return new Response("<html><body></body></html>");
        return $this->render('song/show.html.twig', [
            'song' => $song,
        ]);
    }
    #[Route(path: '/edit', name: 'song_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Song $song) : Response
    {
        $form = $this->createForm(SongType::class, $song);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->managerRegistry->getManager()->flush();

            return $this->redirectToRoute('song_index');
        }
        return $this->render('song/edit.html.twig', [
            'song' => $song,
            'form' => $form->createView(),
        ]);
    }
    #[Route(path: '/delete', name: 'song_delete', methods: ['DELETE'])]
    public function delete(Request $request, Song $song) : Response
    {
        if ($this->isCsrfTokenValid('delete'.$song->getId(), $request->request->get('_token'))) {
            $entityManager = $this->managerRegistry->getManager();
            $entityManager->remove($song);
            $entityManager->flush();
        }
        return $this->redirectToRoute('song_index');
    }
}
