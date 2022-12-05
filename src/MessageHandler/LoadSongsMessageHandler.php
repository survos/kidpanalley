<?php

namespace App\MessageHandler;

use App\Message\LoadSongsMessage;
use App\Services\AppService;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class LoadSongsMessageHandler implements MessageHandlerInterface
{
    public function __construct(private AppService $appService)
    {
    }

    public function __invoke(LoadSongsMessage $message)
    {
        $this->appService->loadSongs();

        // do something with your message
    }
}
