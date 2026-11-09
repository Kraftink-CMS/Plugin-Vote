<?php

namespace KraftInk\Plugins\Vote\Listeners\Link;

use App\Events\AcknowledgingLinkCommand;
use App\Events\CollectingLinkCommands;
use App\Kraft\Link\DeliveryQueue;
use KraftInk\Plugins\Vote\Models\Delivery\Delivery;

class DeliveryListener
{
    public function __construct(private DeliveryQueue $queue)
    {
    }

    public function collect(CollectingLinkCommands $event): void
    {
        $this->queue->collect($event, 'vote', Delivery::query()->where('channel', 'command'));
    }

    public function acknowledge(AcknowledgingLinkCommand $event): void
    {
        $this->queue->acknowledge($event, 'vote', Delivery::query()->where('channel', 'command'));
    }
}
