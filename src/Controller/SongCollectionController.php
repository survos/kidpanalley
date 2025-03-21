<?php

namespace App\Controller;

use App\Entity\Song;
use App\Entity\Video;
use App\Form\SongType;
use App\Repository\SongRepository;
use Survos\ApiGrid\Service\MeiliService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/songs')]
class SongCollectionController extends AbstractController
{
    public function __construct(
        private MeiliService $meiliService,
        private readonly \Doctrine\Persistence\ManagerRegistry $managerRegistry)
    {

    }
    #[Route(path: '/browse/{apiRoute}', name: 'song_index', methods: ['GET'])]
    public function index(string $apiRoute=Song::MEILI_ROUTE) : Response
    {
//        $this->meiliService->getConfig()
        return $this->render('song/index.html.twig', [
                'apiRoute' => $apiRoute,
                'class' => Song::class,
        ]);
    }

    // browse with meili
//    #[Route(path: '/meili', name: 'song_browse', methods: ['GET'])]
//    #[Route(path: '/doctrine', name: 'song_browse_with_doctrine', methods: ['GET'])]
//    public function browse(Request $request) : Response
//    {
//        return $this->render('song/meili.html.twig', [
//            'useMeili' => $request->get('_route') == 'song_browse',
//            'class' => Song::class,
//        ]);
//    }



    #[Route(path: '/new', name: 'song_new', methods: ['GET', 'POST'])]
    public function new(Request $request) : Response
    {
        $song = new Song();
        $form = $this->createForm(SongType::class, $song);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->managerRegistry->getManager();
            $entityManager->persist($song);
            $entityManager->flush();

            return $this->redirectToRoute('song_index');
        }
        return $this->render('song/new.html.twig', [
            'song' => $song,
            'form' => $form->createView(),
        ]);
    }
}
