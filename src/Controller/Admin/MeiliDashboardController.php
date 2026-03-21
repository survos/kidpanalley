<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use Survos\MeiliBundle\Controller\AbstractMeiliController;

#[AdminDashboard(routePath: '/ez-meili', routeName: self::MEILI_ROUTE)]
final class MeiliDashboardController extends AbstractMeiliController
{
    // configureDashboard(), configureMenuItems(), configureAssets() and all
    // meili routes are inherited from AbstractMeiliController.
}
