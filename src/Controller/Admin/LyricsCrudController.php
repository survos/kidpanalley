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
        yield TextField::new('parent');
        yield TextField::new('file');
        yield TextareaField::new('text')->onlyOnDetail()->setLabel('ChordPro Content');
        
        // Show parsed metadata from ChordPro
        yield Field::new('title', 'Title')->onlyOnDetail()->formatValue(function ($value, $entity) {
            $song = $entity->getChordPro();
            return $song?->meta['title'] ?? 'N/A';
        });
        
        yield Field::new('artist', 'Artist')->onlyOnDetail()->formatValue(function ($value, $entity) {
            $song = $entity->getChordPro();
            return $song?->meta['artist'] ?? 'N/A';
        });
        
        yield Field::new('key', 'Key')->onlyOnDetail()->formatValue(function ($value, $entity) {
            $song = $entity->getChordPro();
            return $song?->meta['key'] ?? 'N/A';
        });
        
        // Show formatted lyrics with chords
        yield Field::new('formattedLyrics', 'Formatted Lyrics')->onlyOnDetail()->formatValue(function ($value, $entity) {
            $song = $entity->getChordPro();
            if (!$song) {
                return 'No ChordPro data available';
            }
            
            $output = [];
            foreach ($song->lines as $line) {
                $lineText = '';
                foreach ($line->parts as $part) {
                    if ($part->type === 'lyrics') {
                        $lineText .= $part->text;
                    } elseif ($part->type === 'chord') {
                        $lineText .= '[' . $part->chord . ']';
                    }
                }
                if (!empty(trim($lineText))) {
                    $output[] = $lineText;
                }
            }
            
            return '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';
        });
        
        // Keep the old lyrics array for backward compatibility (hide by default)
        yield ArrayField::new('lyrics')->onlyOnDetail()->setLabel('Legacy Lyrics Array');
    }

    public static function getEntityFqcn(): string
    {
        return \App\Entity\Lyrics::class;
    }
}
