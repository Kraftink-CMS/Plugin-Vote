<?php

namespace KraftInk\Plugins\Vote\Services\Providers;

use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Data\ProviderChallenge;
use KraftInk\Plugins\Vote\Data\VoteContext;
use KraftInk\Plugins\Vote\Data\VoteProof;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;
use InvalidArgumentException;

class ListForge extends MinecraftMp
{
    public const array SERVICES = [
        'minecraft-pocket' => ['minecraftpocket-servers.com', 'Minecraft Pocket Servers', 'minecraft', 'username'],
        'gmod-servers' => ['gmod-servers.com', 'Garry’s Mod Servers', 'garrysmod', 'steamid'],
        'ark-servers' => ['ark-servers.net', 'ARK Servers', 'ark', 'steamid'],
        'rust-servers' => ['rust-servers.net', 'Rust Servers', 'rust', 'steamid'],
        'sevendaystodie-servers' => ['7daystodie-servers.com', '7 Days to Die Servers', 'sevendaystodie', 'steamid'],
        'unturned-servers' => ['unturned-servers.net', 'Unturned Servers', 'unturned', 'steamid'],
        'tf2-servers' => ['tf2-servers.com', 'Team Fortress 2 Servers', 'teamfortress2', 'steamid'],
        'counter-strike-servers' => [
            'counter-strike-servers.net',
            'Counter-Strike Servers',
            'counterstrike2',
            'steamid',
        ],
        'minecraft-server-eu' => ['minecraft-server.eu', 'Minecraft Server Europe', 'minecraft', 'username'],
    ];

    private array $service;

    public function __construct(private readonly string $identifier)
    {
        $this->service =
            self::SERVICES[$identifier] ?? throw new InvalidArgumentException('Unknown ListForge service.');
    }

    public function id(): string
    {
        return $this->identifier;
    }

    public function name(): string
    {
        return $this->service[1];
    }

    public function games(): array
    {
        return [$this->service[2]];
    }

    protected function apiHosts(): array
    {
        return [$this->service[0]];
    }

    public function validUrl(string $url): bool
    {
        return $this->httpsHost($url, [$this->service[0], 'www.'.$this->service[0]]) &&
            ($this->identifier === 'minecraft-server-eu' ||
                preg_match('~^/server/[0-9]+/vote/?$~D', parse_url($url, PHP_URL_PATH) ?? '') === 1);
    }

    public function prepare(object $site, VoteContext $context): ProviderChallenge
    {
        $identity = $this->identity($context->player, $context->uuid);

        return new ProviderChallenge(
            $site->url.
                (str_contains($site->url, '?') ? '&' : '?').
                http_build_query([$this->service[3] => $identity]),
        );
    }

    public function verify(object $site, object $challenge): VoteProof
    {
        $identity = $this->identity($challenge->player_name, $challenge->player_uuid);
        $query = [
            'object' => 'votes',
            'element' => 'claim',
            'key' => $this->configuration($site)['api_key'],
            $this->service[3] => $identity,
        ];
        $url = 'https://'.$this->service[0].($this->identifier === 'minecraft-server-eu' ? '/api/v1/' : '/api/');
        $state = trim($this->request('GET', $url, $query)->body());
        if ($state === '2' && $challenge->provider_claimed_at) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.verification.claim_uncertain')]);
        }
        $this->assertConfirmed($state === '1');
        Challenge::query()
            ->where('id', $challenge->id)
            ->update(['provider_claimed_at' => now(), 'verification_state' => 'claiming']);
        $this->assertConfirmed(trim($this->request('POST', $url, ['action' => 'post'] + $query)->body()) === '1');

        return new VoteProof($this->id().':'.hash('sha256', strtolower($identity).':'.$challenge->id));
    }

    private function identity(string $player, ?string $uuid): string
    {
        $identity = $this->service[3] === 'steamid' ? $uuid : $player;
        $valid =
            is_string($identity) &&
            ($this->service[3] === 'steamid'
                ? preg_match('/^[0-9]{17}$/D', $identity) &&
                    strcmp($identity, '76561197960265728') >= 0 &&
                    strcmp($identity, '76561202255233023') <= 0
                : preg_match('/^[a-zA-Z0-9_ .-]{1,32}$/D', $identity));
        $this->assertConfirmed($valid);

        return $identity;
    }
}
