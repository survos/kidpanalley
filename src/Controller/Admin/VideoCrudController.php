<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class VideoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return \App\Entity\Video::class;
    }
}
