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
    }

    /*
    * @return array The event names to listen to
    */
    public static function getSubscribedEvents(): array
    {
        return [
            KnpMenuEvent::MENU_EVENT => 'onMenuEvent',
            KnpMenuEvent::SIDEBAR_MENU_EVENT => 'onMenuEvent',
        ];
    }
}
