<?php

namespace App\Menu;

use App\Entity\Colecta;
use App\Entity\Distrito;
use App\Entity\Especie;
use App\Entity\Familia;
use App\Entity\Genero;
use App\Entity\Location;
use App\Entity\Muestra;
use App\Entity\Municipio;
use App\Entity\Planta;
use App\Entity\Project;
use App\Entity\Region;
use App\Entity\Tax;
use App\Service\AppService;
use Doctrine\Persistence\ManagerRegistry;
use Survos\BaseBundle\Menu\AdminMenuInterface;
use Survos\BaseBundle\Menu\AdminMenuTrait;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;
use Umbrella\AdminBundle\Menu\BaseAdminMenu;
use Umbrella\AdminBundle\UmbrellaAdminConfiguration;
use Umbrella\CoreBundle\Menu\Builder\MenuBuilder;
use Umbrella\CoreBundle\Menu\Builder\MenuItemBuilder;
use Umbrella\CoreBundle\Menu\DTO\MenuItem;
use function Symfony\Component\String\u;

class AdminMenu extends BaseAdminMenu implements AdminMenuInterface
{
    use AdminMenuTrait;

    public function __construct(private AuthorizationCheckerInterface $security,
                                protected Environment                 $twig,
                                private ManagerRegistry $managerRegistry,
                                protected UmbrellaAdminConfiguration  $configuration,
    )
    {
        parent::__construct($this->twig, $configuration);
    }

    public function buildMenu(MenuBuilder $builder, array $options)
    {

        $options = (new OptionsResolver())
            ->setDefaults([
                'jurisdiction' => null
            ])->resolve($options);

        $rootMenu = $builder->root();


        $lvl = 0;
        $taxMenu = $this->addMenuItem($rootMenu, ['label' => 'Songs', 'style' => 'header', 'icon' => 'fas fa-home']);

        $this->addMenuItem($taxMenu, ['route' => 'song_index', 'label' => "Songs", 'icon' => 'fas fa-home']);
        return;
        $this->addMenuItem($taxMenu, ['label' => 'Nueva con wiki', 'route' => 'wiki_new_entity']);


//        if ($this->security->isGranted('ROLE_ADMIN')) {
//            $this->addMenuItem($rootMenu, ['label' => 'Load Locations', 'route' => 'app_wiki_test']);
////            $this->addMenuItem($rootMenu, ['label' => 'Mun Index', 'route' => 'municipio_index', 'rp' => ['shortClass' => 'location']]);
//        }

        $subMenu = $this->addMenuItem($rootMenu, ['label' => 'Locations', 'style' => 'header', 'icon' => 'fas fa-map']);
        foreach ($this->getLocationRanks() as $locationRank=>$count) {
            $this->addMenuItem($subMenu, ['label' => $locationRank, 'route' => 'location_browse',
                'badge' => $count,
                'rp' => ['locationRank' => $locationRank]]);
        }
        $this->addMenuItem($subMenu, ['label' => 'All', 'route' => 'location_browse']);
        // @todo: cache system resources, like tax and location counts


        $this->addMenuItem($rootMenu, ['label' => 'Projects',
            'route' => 'project_index',
            'badge' => $this->managerRegistry->getRepository(Project::class)->count([])
            ]);
        $workflowMenu = $this->addMenuItem($rootMenu, ['label' => 'Workflows', 'style' => 'header', 'icon' => 'fas fa-diagram-project']);
        foreach ($this->workflowHelper->getWorkflowsByCode() as $code => $workflow) {
            $this->addMenuItem($workflowMenu, ['label' => $code, 'route' => 'survos_workflow', 'rp' => ['flowCode' => $code]]);

        }

//        foreach ($this->appService->getScienceClasses() as $class=>$label) {
//        foreach (Tax::HIER as $label) {
//            $classMenu = $this->addMenuItem($rootMenu, [
//                'route' => 'tax_browse',
//                'rp' => ['taxonRank' => $label],
//                'label' => $label
//                ]);
//        }


        $this->addMenuItem($rootMenu, ['route' => 'app_homepage', 'label' => "Home", 'icon' => 'fas fa-home']);

        // why
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $this->addMenuItem($rootMenu, ['route' => 'app_admin_overview', 'label' => "Counts", 'icon' => 'fas fa-tally']);
        }

        $this->addMenuItem($rootMenu, ['route' => 'api_entrypoint', 'label' => "API", 'icon' => 'fas fa-exchange']);
        $this->addMenuItem($rootMenu, ['label' => 'Documenacion', 'icon' => 'fal fa-book-open-reader', 'external' => true, 'route' => 'app_docs']);


    }

}
