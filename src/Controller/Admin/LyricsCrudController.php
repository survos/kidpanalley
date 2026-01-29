<?php

namespace App\Controller\Admin;

use App\Entity\Lyrics;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use App\Field\MusicDisplayField;
use Survos\EzBundle\Controller\BaseCrudController;

class LyricsCrudController extends BaseCrudController
{
    public function configureFields(string $pageName): iterable
    {
        // Index fields - show key metadata
        yield TextField::new('code')->setLabel('Code');
        yield TextField::new('title')->setLabel('Title');
        yield TextField::new('artist')->setLabel('Artist');
        yield TextField::new('key')->setLabel('Key');
        yield TextField::new('parent')->setLabel('Parent');

        // Detail fields
        yield TextField::new('file')->onlyOnDetail();
        yield TextField::new('title')->onlyOnDetail();
        yield TextField::new('artist')->onlyOnDetail();
        yield TextField::new('key')->onlyOnDetail();
        yield TextField::new('album')->onlyOnDetail();
        yield TextField::new('year')->onlyOnDetail();
        yield TextField::new('subtitle')->onlyOnDetail();
        yield TextField::new('composer')->onlyOnDetail();
        yield TextField::new('lyricist')->onlyOnDetail();
        yield TextField::new('copyright')->onlyOnDetail();

        yield TextareaField::new('text')->onlyOnDetail()->setLabel('ChordPro Content');

        // Show lyrics from chordProData
        yield Field::new('lyricsAsString', 'Lyrics')->onlyOnDetail()->formatValue(function ($value) {
            return '<pre>' . htmlspecialchars($value) . '</pre>';
        });

        // Show formatted lyrics with chords using stimulus controller
        yield Field::new('musicSheet', 'Music Sheet')
            ->setVirtual(true)
            ->formatValue(function ($value, $entity) {
            if (!$entity->text) {
                return '<div class="alert alert-warning">No ChordPro data available</div>';
            }

            $url = $this->urlGenerator->generate('meili_admin_app_lyrics_music', ['code' => $entity->code]);
            return $url;
            return sprintf('<a href="%s" class="btn btn-primary" target="_blank">View Music Sheet</a>', $url);
        });

    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);
        $myAction = Action::new('myAction', 'Lyrics', 'fa fa-link')
            ->linkToRoute('meili_admin_app_lyrics_music', fn (Lyrics $entity) => ['code' => $entity->code])
            ->displayIf(fn (Lyrics $entity) => $entity->text)
        ; // optional

        return $actions
            ->add(Crud::PAGE_INDEX, $myAction)
            ->add(Crud::PAGE_DETAIL, $myAction);
    }

    public static function getEntityFqcn(): string
    {
        return \App\Entity\Lyrics::class;
    }
}
