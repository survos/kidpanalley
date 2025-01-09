<?php

namespace App\Controller;

use App\Form\SongType;
use Limenius\Liform\LiformInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Limenius\Liform\Resolver;
use Limenius\Liform\Liform;
use Limenius\Liform\Transformer\StringTransformer;
use Limenius\Liform\Transformer;

class LiformController extends AbstractController
{
    #[Route('/liform', name: 'app_liform')]
    public function index(LiformInterface $liform): JsonResponse
    {
        $songForm = $this->createForm(SongType::class);
        $schema = $liform->transform($songForm);
        dd($schema);
        $schema = json_encode($this->get('liform')->transform($form));

        $schema =
        $form = new FormBuilder('car');

        $stringTransformer = new StringTransformer();
        $resolver = new Resolver();
        $resolver->setTransformer('text', $stringTransformer);
        $resolver->setTransformer('textarea', $stringTransformer, 'textarea');
// more transformers you might need, for a complete list of what is used in Symfony
// see https://github.com/Limenius/LiformBundle/blob/master/Resources/config/transformers.xml
        $liform = new Liform($resolver);

        $form = $this->createForm(CarType::class, $car, ['csrf_protection' => false]);
        $schema = json_encode($liform->transform($form));

        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/LiformController.php',
        ]);
    }
}
