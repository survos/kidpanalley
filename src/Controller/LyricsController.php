<?php

namespace App\Controller;

use App\Entity\Lyrics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/lyrics')]
class LyricsController extends AbstractController
{
    #[Route('/{code}.cho', name: 'lyrics_raw')]
    public function raw(Lyrics $lyrics): Response
    {
        if (!$lyrics->text) {
            return new Response('No ChordPro data available', 404);
        }


        return new Response($lyrics->text, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
//            'Content-Disposition' => sprintf('attachment; filename="%s.cho"', $lyrics->getCode())
        ]);
    }
}
