<?php

namespace App\Controller;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\FileAsset;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/file-assets')]
class FileAssetController extends AbstractController
{
    #[Route(path: '/browse', name: 'file_asset_browse', methods: ['GET'])]
    public function browse(IriConverterInterface $iriConverter): Response
    {
        $apiGetCollectionUrl = $iriConverter->getIriFromResource(
            FileAsset::class,
            operation: new GetCollection(name: FileAsset::DOCTRINE_ROUTE)
        );

        return $this->render('file_asset/browse.html.twig', [
            'class' => FileAsset::class,
            'apiGetCollectionUrl' => $apiGetCollectionUrl,
        ]);
    }
}
