<?php

namespace App\EventSubscriber;

use Knp\Menu\ItemInterface;
use Survos\BootstrapBundle\Event\KnpMenuEvent;
use Survos\BootstrapBundle\Menu\MenuBuilder;
use Survos\BootstrapBundle\Traits\KnpMenuHelperTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AppMenuSubscriber implements EventSubscriberInterface
{
    use KnpMenuHelperTrait;

    public function __construct(private ?AuthorizationCheckerInterface $security=null)
    {
    }

    public function onMenuEvent(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $options = $event->getOptions();
        $taxMenu = $this->addMenuItem($menu, ['label' => 'Songs', 'style' => 'header', 'icon' => 'fas fa-home']);

        $this->addMenuItem($menu, ['route' => 'song_index', 'label' => "Songs", 'icon' => 'fas fa-home']);
        $this->addMenuItem($menu, ['route' => 'video_index', 'label' => "Videos", 'icon' => 'fas fa-home']);

        $this->addMenuItem($menu, ['route' => 'app_homepage']);
        // for nested menus, don't add a route, just a label, then use it for the argument to addMenuItem
        $nestedMenu = $this->addMenuItem($menu, ['label' => 'Credits']);
        foreach (['bundles', 'javascript'] as $type) {
            // $this->addMenuItem($nestedMenu, ['route' => 'survos_base_credits', 'rp' => ['type' => $type], 'label' => ucfirst($type)]);
            $this->addMenuItem($nestedMenu, ['uri' => "#type" , 'label' => ucfirst($type)]);
        }

    }

    /*
    * @return array The event names to listen to
    */
    public static function getSubscribedEvents()
    {
        return [
            KnpMenuEvent::MENU_EVENT => 'onMenuEvent',
            KnpMenuEvent::SIDEBAR_MENU_EVENT => 'onMenuEvent',
        ];
    }
}
