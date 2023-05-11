<?php

namespace App\EventListener;

use Survos\BootstrapBundle\Event\KnpMenuEvent;
use Survos\BootstrapBundle\Traits\KnpMenuHelperTrait;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsEventListener(event: KnpMenuEvent::SIDEBAR_MENU_EVENT, method: 'appSidebarMenu')]
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
        $options = $event->getOptions();

        $this->addMenuItem($menu, ['route' => 'song_index', 'label' => "Songs", 'icon' => 'fas fa-home']);
        $this->addMenuItem($menu, ['route' => 'song_browse', 'label' => "Song Search", 'icon' => 'fas fa-search']);
        $this->addMenuItem($menu, ['route' => 'video_index', 'label' => "Videos", 'icon' => 'fas fa-home']);
        $this->addMenuItem($menu, ['route' => 'video_browse', 'label' => "Videos (API)", 'icon' => 'fas fa-sync']);
        $this->addMenuItem($menu, ['route' => 'survos_commands', 'label' => "Commands", 'icon' => 'fas fa-terminal']);

        $this->addMenuItem($menu, ['route' => 'app_homepage']);
        // for nested menus, don't add a route, just a label, then use it for the argument to addMenuItem
        $nestedMenu = $this->addMenuItem($menu, ['label' => 'Credits']);
        foreach (['bundles', 'javascript'] as $type) {
            // $this->addMenuItem($nestedMenu, ['route' => 'survos_base_credits', 'rp' => ['type' => $type], 'label' => ucfirst($type)]);
            $this->addMenuItem($nestedMenu, ['uri' => "#type" , 'label' => ucfirst($type)]);
        }
        $this->addHeading($menu, 'auth');
        $this->authMenu($this->security, $menu);
    }

}
