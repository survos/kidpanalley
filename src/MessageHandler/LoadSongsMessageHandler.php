<?php

namespace App\MessageHandler;

use App\Message\LoadSongsMessage;
use App\Services\AppService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class LoadSongsMessageHandler implements MessageHandlerInterface
{
    public function __construct(
        private AppService                                 $appService,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    )
    {
    }

    public function __invoke(LoadSongsMessage $message)
    {
        $this->appService->loadExistingSongs();
        $this->appService->loadSongs();

        $dir = $this->projectDir . '/../../survos/data/kpa/Lyrics individual songs';
        $this->appService->loadLyrics($dir);


        // do something with your message
    }
}
