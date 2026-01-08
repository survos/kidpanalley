<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Survos\CoreBundle\Controller\BaseCrudController;

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

        // Show formatted lyrics with chords
        yield Field::new('formattedLyrics', 'Formatted Lyrics with Chords')->onlyOnDetail()->formatValue(function ($value, $entity) {
            $song = $entity->getChordPro();
            if (!$song) {
                return 'No ChordPro data available';
            }

            return (new \ChordPro\Formatter\HtmlFormatter())->format($song, []);
        });
    }

    public static function getEntityFqcn(): string
    {
        return \App\Entity\Lyrics::class;
    }
}
