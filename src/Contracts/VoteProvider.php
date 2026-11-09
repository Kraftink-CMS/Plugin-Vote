<?php

namespace KraftInk\Plugins\Vote\Contracts;

use KraftInk\Plugins\Vote\Data\ProviderChallenge;
use KraftInk\Plugins\Vote\Data\VoteContext;
use KraftInk\Plugins\Vote\Data\VoteProof;

interface VoteProvider
{
    public function id(): string;

    public function name(): string;

    public function games(): array;

    public function mode(): string;

    public function fields(): array;

    public function validUrl(string $url): bool;

    public function minimumCooldown(): int;

    public function prepare(object $site, VoteContext $context): ProviderChallenge;

    public function verify(object $site, object $challenge): VoteProof;
}
