<?php

namespace App\EventSubscriber;

use Survos\BaseBundle\Menu\BaseMenuSubscriber;
use Survos\BaseBundle\Menu\MenuBuilder;
use Survos\BaseBundle\Traits\KnpMenuHelperTrait;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Survos\BaseBundle\Event\KnpMenuEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class KnpMenuSubscriber extends BaseMenuSubscriber implements EventSubscriberInterface
{
    use KnpMenuHelperTrait;

    public function __construct(
        /**
         * @param AuthorizationCheckerInterface $security
         */
        private readonly AuthorizationCheckerInterface $security,
        private readonly ParameterBagInterface $parameterBag
    )
    {
    }

    public function onKnpMenuEvent(KnpMenuEvent $event)
    {
        $env = null;
        $menu = null;
        $songMenu = null;
        $isAdmin = null;
        $videoMenu = null;
        $isSuperAdmin = null;
        dd($event);
        $env = $this->parameterBag->get('kernel.environment');
        $isAdmin = ('dev' == $env) || $this->security->isGranted("ROLE_ADMIN");

        $menu = $event->getMenu();
        $menu->addChild('survos_landing', ['label' => 'home', 'route' => 'app_homepage'])->setAttribute('icon', 'fas fa-home');
        // $menu->addChild('songs_credits', ['route' => 'app_credits_page'])->setAttribute('icon', 'fal fa-music');

        $songMenu = $this->addMenuItem($menu, ['id' => 'songs', 'icon' => 'fas fa-music']);
        $this->addMenuItem($songMenu, ['route' => 'song_index']);
        if ($isAdmin) {
            $this->addMenuItem($songMenu, ['route' => 'song_new']);
        }

        $videoMenu = $this->addMenuItem($menu, ['id' => 'videos', 'icon' => 'fab fa-youtube']);
        $videoMenu->addChild('video.list', ['route' => 'video_index']);
        // $videoMenu->addChild('video.new', ['route' => 'video_new']);

        $isSuperAdmin = $this->security->isGranted("ROLE_SUPER_ADMIN");

        if ($isSuperAdmin) {
            $loadMenu = $menu->addChild('load');
            $loadMenu->addChild('app_load_songs', ['route' => 'app_load_songs'])->setAttribute('icon', 'fas fa-home');
            $loadMenu->addChild('app_load_lyrics', ['route' => 'app_load_lyrics'])->setAttribute('icon', 'fas fa-music');
            $loadMenu->addChild('app_load_youtube_channel', ['route' => 'app_load_youtube_channel'])->setAttribute('icon', 'fab fa-youtube');
        }
        $menu->addChild('survos_landing_credits', ['route' => 'survos_landing_credits'])->setAttribute('icon', 'fas fa-trophy');
        $menu->addChild('survos_typography', ['route' => 'app_typography'])->setAttribute('icon', 'fab fa-bootstrap');

        if ($isAdmin) {
            $menu->addChild('admin', ['route' => 'easyadmin'])->setAttribute('icon', 'fas fa-wrench');
            $menu->addChild('heroku', ['route' => 'app_heroku'])->setAttribute('icon', 'fab fa-heroku');
        }

        $this->authMenu($this->security, $menu);


        // ...
    }

    public static function getSubscribedEvents(): array
    {
        return [
//            KnpMenuEvent::class => 'onKnpMenuEvent',
            KnpMenuEvent::SIDEBAR_MENU_EVENT => 'onKnpMenuEvent',
        ];
    }
}
