<?php

namespace App\Message;

final class FetchYoutubeChannelMessage
{
    /*
     * Add whatever properties and methods you need
     * to hold the data for this message class.
     */


     public function __construct(public ?string $name=null)
     {
     }

    public function getName(): string
    {
        return $this->name;
    }
}
