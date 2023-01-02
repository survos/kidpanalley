<?php

namespace App\MessageHandler;

use App\Message\FetchYoutubeChannelMessage;
use App\Services\AppService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class FetchYoutubeChannelMessageHandler implements MessageHandlerInterface
{

    public function __construct(
        private AppService $appService,
        private LoggerInterface $logger,
        private ParameterBagInterface $bag)
    {
    }

    public function __invoke(FetchYoutubeChannelMessage $message)
    {
        // @todo: get filename
        $key = $this->bag->get('youtube_api_key');
        $channel = $this->bag->get('youtube_channel');

        $this->logger->warning("Fetching...");
        $this->appService->fetchYoutubeChannel($key, $channel);

        // do something with your message
    }
}
