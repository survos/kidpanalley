<?php

/*
 * This file is part of the AdminLTE-Bundle demo.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber;

use App\Entity\User;
use Survos\BaseBundle\Event\NavbarUserEvent;
use Survos\BaseBundle\Event\ShowUserEvent;
use Survos\BaseBundle\Event\SidebarUserEvent;
use Survos\BaseBundle\Model\UserModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Security;

class NavbarUserSubscriber implements EventSubscriberInterface
{
    /**
     * @param Security $security
     */
    public function __construct(protected Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NavbarUserEvent::class => ['onShowUser', 100],
            SidebarUserEvent::class => ['onShowUser', 100],
        ];
    }

    public function onShowUser(ShowUserEvent $event)
    {

        if (null === $this->security->getUser()) {
            return;
        }

        /* @var $myUser User */
        $myUser = $this->security->getUser();

        $user = new UserModel();
        $user
            ->setId($myUser->getId())
            ->setName($myUser->getUserIdentifier())
            ->setUsername($myUser->getUserIdentifier())
            ->setIsOnline(true)
            ->setTitle('demo user')
            ->setAvatar('/bundles/adminlte/images/default_avatar.png')
            ->setMemberSince(new \DateTime());

        //$event->setShowProfileLink(false);
        //$event->addLink(new NavBarUserLink('Followers', 'home'));

        $event->setUser($user);
    }
}
