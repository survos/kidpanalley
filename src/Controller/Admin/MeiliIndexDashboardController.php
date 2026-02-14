<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Survos\MeiliBundle\Controller\MeiliAdminController;
use Symfony\Component\HttpFoundation\Response;

final class MeiliIndexDashboardController extends MeiliAdminController
{
    #[AdminRoute(path: '/index/overview/{indexName}', name: 'meili_index_dashboard')]
    public function indexDashboard(AdminContext $context, string $indexName): Response
    {
        return parent::indexDashboard($context, $indexName);
    }
}
