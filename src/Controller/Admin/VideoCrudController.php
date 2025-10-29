<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Survos\CoreBundle\Controller\BaseCrudController;

class VideoCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string
    {
        return \App\Entity\Video::class;
    }
}
