<?php

namespace App\EventListener;

use Survos\BootstrapBundle\Event\KnpMenuEvent;
use Survos\BootstrapBundle\Traits\KnpMenuHelperTrait;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsEventListener(event: KnpMenuEvent::SIDEBAR_MENU_EVENT, method: 'appSidebarMenu')]
#[AsEventListener(event: KnpMenuEvent::NAVBAR_MENU_EVENT, method: 'navbarMenu')]
//#[AsEventListener(event: KnpMenuEvent::PAGE_MENU_EVENT, method: 'coreMenu')]
final class AppMenuEventListener
{
    use KnpMenuHelperTrait;

    public function __construct(
        private ?AuthorizationCheckerInterface $security=null)
    {
        $this->setAuthorizationChecker($this->security);
    }

    public function supports(KnpMenuEvent $event): bool
    {
        return true;
    }
    public function appSidebarMenu(KnpMenuEvent $event): void
    {
        if (!$this->supports($event)) {
            return;
        }
        $menu = $event->getMenu();
    }


    public function navbarMenu(KnpMenuEvent $event): void
    {
        if (!$this->supports($event)) {
            return;
        }
        $menu = $event->getMenu();
        $this->addMenuItem($menu, ['route' => 'song_index', 'label' => "Songs", 'icon' => 'fas fa-home']);
//        $this->addMenuItem($menu, ['route' => 'song_browse', 'label' => "Song Search", 'icon' => 'fas fa-search']);
        $subMenu = $this->addSubmenu($menu, 'songs');
        $this->add($subMenu, 'song_index');
        $this->add($subMenu, 'song_browse');

        $this->addMenuItem($menu, ['route' => 'video_index', 'label' => "Videos", 'icon' => 'fas fa-home']);
        $this->addMenuItem($menu, ['route' => 'video_browse', 'label' => "Videos (API)", 'icon' => 'fas fa-sync']);
        $subMenu = $this->addSubmenu($menu, 'admin');
        $this->add($subMenu, 'survos_commands', label: "Commands");

        $nestedMenu = $this->addMenuItem($menu, ['label' => 'Credits']);
        foreach (['bundles', 'javascript'] as $type) {
            // $this->addMenuItem($nestedMenu, ['route' => 'survos_base_credits', 'rp' => ['type' => $type], 'label' => ucfirst($type)]);
            $this->addMenuItem($nestedMenu, ['uri' => "#type" , 'label' => ucfirst($type)]);
        }
        $this->addHeading($menu, 'auth');
        $this->authMenu($this->security, $menu);
    }

}
