<?php

namespace App\EventListener;

use Behat\Testwork\Event\Event;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Behat\Behat\EventDispatcher\Event\StepTested;
final class BehatListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[AsEventListener(event: StepTested::BEFORE)]
    public function onBeforeStepTested(Event $event): void
    {
        echo "event!";

        $this->logger->error('Event!');
        dd($event);
        // ...
    }

}
