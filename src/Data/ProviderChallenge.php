<?php

namespace KraftInk\Plugins\Vote\Data;

class ProviderChallenge
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $token = null,
        public readonly int $expiresSeconds = 3600,
        public readonly ?string $publicCode = null,
    ) {
    }
}
