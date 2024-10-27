<?php

namespace App\EventListener;

use App\Entity\Song;
use App\Entity\Video;
use Knp\Menu\ItemInterface;
use Survos\ApiGrid\Service\DatatableService;
use Survos\ApiGrid\Service\MeiliService;
use Survos\ApiGrid\State\MeiliSearchStateProvider;
use Survos\BootstrapBundle\Event\KnpMenuEvent;
use Survos\BootstrapBundle\Service\ContextService;
use Survos\BootstrapBundle\Service\MenuService;
use Survos\BootstrapBundle\Traits\KnpMenuHelperTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

//#[AsEventListener(event: KnpMenuEvent::SIDEBAR_MENU, method: 'appSidebarMenu')]
#[AsEventListener(event: KnpMenuEvent::NAVBAR_MENU, method: 'navbarMenu')]
#[AsEventListener(event: KnpMenuEvent::FOOTER_MENU, method: 'footerMenu')]
#[AsEventListener(event: KnpMenuEvent::AUTH_MENU, method: 'ourAuthMenu')]
#[AsEventListener(event: KnpMenuEvent::PAGE_MENU, method: 'pageMenu')]
//#[AsEventListener(event: KnpMenuEvent::PAGE_MENU_EVENT, method: 'coreMenu')]
final class AppMenuEventListener
{
    use KnpMenuHelperTrait;

    public function __construct(
        #[Autowire('%kernel.environment%')] protected string $env,
        private ContextService $contextService,
        private Security $security,
        private MenuService $menuService,
        private DatatableService $datatableService,
        // why is autowire required?
        #[Autowire(service: 'api_meili_service')]  private MeiliService $meiliService,
        private ?AuthorizationCheckerInterface $authorizationChecker=null
    )
    {
//        $this->setAuthorizationChecker($this->authorizationChecker);
    }

    public function supports(KnpMenuEvent $event): bool
    {
        return true;
    }

    public function ourAuthMenu(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $this->menuService->addAuthMenu($menu);
    }

    public function pageMenu(KnpMenuEvent $event): void
    {
        if (!$this->supports($event)) {
            return;
        }
        $menu = $event->getMenu();

        if ($entityClass = $event->getOption('entityClass')) {
            $settings = $this->datatableService->getSettingsFromAttributes($entityClass);
            foreach ($settings as $fieldName => $setting) {
                if ($setting['browsable']??false) {
                    $this->add($menu, 'survos_facet_show', ['indexName' => MeiliSearchStateProvider::getSearchIndexObject($entityClass), 'fieldName' => $fieldName], label: $fieldName);
                }
            }
        }

        if ($columnsJson = $event->getOption('columns')) {
            $columns = json_decode($columnsJson);
            foreach ($columns as $column) {
                $this->add($menu, 'app_homepage', ['field' => $column->name], label: $column->name);
            }
        }
        $video = $event->getOption('video');
        if ($video) {
            $this->add($menu, 'video_show', $video);
            $this->add($menu, 'video_edit', $video);
        }
    }

        public function footerMenu(KnpMenuEvent $event): void
    {
        if (!$this->supports($event)) {
            return;
        }
        $menu = $event->getMenu();

//        $subMenu = $this->addSubmenu($menu, 'songs');
//
//        $this->add($subMenu, 'song_index');
//        $this->add($subMenu, 'song_browse');

        $theme = $this->contextService->getOption('theme');
        $this->add($menu, 'app_homepage');
        $this->add($menu, 'app_load_lyrics');
        $this->add($menu, 'survos_commands');
        $this->add($menu, uri: '#', label: 'theme: ' . $theme);
        // it should be possible to do this in twig, not here.
        $this->add($menu, id: 'copyright',

            label: 'Data Copyright &copy; <b>Kid Pan Alley</b> All rights reserved.');
    }


    public function navbarMenu(KnpMenuEvent $event): void
    {
        if (!$this->supports($event)) {
            return;
        }
        $menu = $event->getMenu();
//        $this->addMenuItem($menu, ['route' => 'song_index', 'label' => "Songs", 'icon' => 'fas fa-home']);
//        $this->addMenuItem($menu, ['route' => 'song_browse', 'label' => "Song Search", 'icon' => 'fas fa-search']);
//        $subMenu = $this->addSubmenu($menu, 'songs');
//        $subMenu->setExtra('btn', 'btn btn-danger');
//        dump($subMenu);
//        // either a button on a navlink
//        $subMenu->setLinkAttribute('class', 'nav-link');

        foreach (['Song','Video'] as $shortClass) {
            $this->add($menu, 'app_browse_with_doctrine', ['shortClass' => $shortClass], label: '@sql ' . $shortClass);
            $this->add($menu, 'app_browse', ['shortClass' => $shortClass], label: '@meili ' . $shortClass);
        }

        //
        $subMenu = $this->addSubmenu($menu, '@commands');
        foreach ([Song::class, Video::class] as $class) {
            $this->add($subMenu, 'survos_command', [
                'commandName'=>'grid:index',
                'class' => $class
            ],
                label: 'grid:index ' .  $class
            );
        }
        $this->add($subMenu, 'survos_commands');

        $subMenu = $this->addSubmenu($menu, '@old');
        $this->add($subMenu, 'song_index', label: 'Songs (html)');
        $this->add($subMenu, 'song_browse', label: 'Songs-Meili');
        $this->add($subMenu, 'song_browse_with_doctrine', label: 'Songs-Doctine');
        $this->add($subMenu, 'video_browse', label: 'Youtube Videos');
//        $this->add($menu, 'video_index'); // in-memory
//        $this->add($subMenu, 'song_browse');

//        $this->addMenuItem($menu, ['route' => 'video_index', 'label' => "Videos", 'icon' => 'fas fa-home']);
//        $this->addMenuItem($menu, ['route' => 'video_index', 'label' => "Videos (API)", 'icon' => 'fas fa-sync']);
        if ($this->env === 'dev' && $this->security->isGranted('ROLE_ADMIN')) {
            $subMenu = $this->addSubmenu($menu, 'admin');
            $this->add($subMenu, 'survos_commands', label: "Commands");
        }

//        $nestedMenu = $this->addMenuItem($menu, ['label' => 'Credits']);
//        foreach (['bundles', 'javascript'] as $type) {
//            // $this->addMenuItem($nestedMenu, ['route' => 'survos_base_credits', 'rp' => ['type' => $type], 'label' => ucfirst($type)]);
//            $this->addMenuItem($nestedMenu, ['uri' => "#type" , 'label' => ucfirst($type)]);
//        }
    }

}
