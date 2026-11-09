<?php

namespace KraftInk\Plugins\Vote\Data;

class VoteProof
{
    public function __construct(public readonly string $eventId, public readonly ?int $votedAt = null)
    {
    }
}
