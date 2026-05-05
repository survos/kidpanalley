<?php

namespace App\Controller;

use App\Entity\FileAsset;
use Survos\FieldBundle\Attribute\ControllerMeta;
use Survos\FieldBundle\Attribute\RouteMeta;
use Survos\FieldBundle\Enum\Audience;
use Survos\FieldBundle\Enum\Purpose;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/file-assets')]
#[ControllerMeta(entity: FileAsset::class, audience: Audience::Public, tags: ['catalog', 'files'])]
class FileAssetController extends AbstractController
{
    #[Route(path: '/browse', name: 'file_asset_browse', methods: ['GET'])]
    #[RouteMeta(
        description: 'Browse and search imported source files through the Doctrine-backed API grid.',
        purpose: Purpose::List,
        sitemap: true,
        changefreq: 'weekly',
        priority: 0.7
    )]
    public function browse(): Response
    {
        return $this->render('file_asset/browse.html.twig', [
            'class' => FileAsset::class,
        ]);
    }
}
