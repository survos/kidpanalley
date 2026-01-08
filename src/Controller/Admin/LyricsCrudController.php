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

        // Show formatted lyrics with chords using stimulus controller
        yield Field::new('formattedLyrics', 'Formatted Lyrics with Chords')->onlyOnDetail()->formatValue(function ($value, $entity) {
            if (!$entity->getText()) {
                return 'No ChordPro data available';
            }

            $url = $this->generateUrl('lyrics_raw', ['code' => $entity->getCode()]);
            return sprintf('
                <div data-controller="music-display" 
                     data-music-display-url-value="%s" 
                     data-music-display-width-value="800" 
                     data-music-display-height-value="600"
                     style="min-height: 400px; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 1rem;"
                     class="text-center text-muted">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading music...</span>
                    </div>
                    <p class="mt-2">Loading sheet music...</p>
                </div>', 
                $url
            );
        });
    }

    public static function getEntityFqcn(): string
    {
        return \App\Entity\Lyrics::class;
    }
}
