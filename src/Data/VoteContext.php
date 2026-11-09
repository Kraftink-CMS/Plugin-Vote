<?php

namespace KraftInk\Plugins\Vote\Data;

class VoteContext
{
    public function __construct(
        public readonly int $userId,
        public readonly string $player,
        public readonly ?string $uuid,
        public readonly string $ip,
        public readonly string $nonce,
    ) {
    }
}
