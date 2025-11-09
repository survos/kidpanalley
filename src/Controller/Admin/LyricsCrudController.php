<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Survos\CoreBundle\Controller\BaseCrudController;

class LyricsCrudController extends BaseCrudController
{

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('parent');
        yield TextField::new('file');
        yield ArrayField::new('lyrics')->onlyOnDetail();
    }

    public static function getEntityFqcn(): string
    {
        return \App\Entity\Lyrics::class;
    }
}
