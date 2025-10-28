<?php

namespace App\Form;

use App\Entity\Song;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SongType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('date')
            ->add('year')
            ->add('school')
            ->add('lyrics')
            ->add('featuredArtist')
            ->add('recordingCredits')
            ->add('musicians')
            ->add('writers')
            ->add('wordpressPageId')
            ->add('recording')
            ->add('publisher')
            ->add('notes')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Song::class,
        ]);
    }
}
