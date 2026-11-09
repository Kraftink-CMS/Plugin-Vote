<?php

namespace KraftInk\Plugins\Vote\Services\Providers;

use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Contracts\VoteProvider;
use KraftInk\Plugins\Vote\Data\ProviderChallenge;
use KraftInk\Plugins\Vote\Data\VoteContext;
use KraftInk\Plugins\Vote\Data\VoteProof;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;

class MinecraftMp extends HttpProvider implements VoteProvider
{
    public function id(): string
    {
        return 'minecraft-mp';
    }

    public function name(): string
    {
        return 'Minecraft-MP';
    }

    public function games(): array
    {
        return ['minecraft'];
    }

    public function mode(): string
    {
        return 'poll';
    }

    public function minimumCooldown(): int
    {
        return 1440;
    }

    public function fields(): array
    {
        return [
            'api_key' => [
                'type' => 'secret',
                'label' => 'vote::messages.verification.api_key',
                'rules' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{8,200}$/D'],
            ],
        ];
    }

    public function validUrl(string $url): bool
    {
        return $this->httpsHost($url, ['minecraft-mp.com', 'www.minecraft-mp.com']) &&
            (bool) preg_match('~^/server/[0-9]+/vote/?$~D', parse_url($url, PHP_URL_PATH) ?? '');
    }

    public function prepare(object $site, VoteContext $context): ProviderChallenge
    {
        $this->assertConfirmed((bool) preg_match('/^[a-zA-Z0-9_]{1,16}$/D', $context->player));

        return new ProviderChallenge(
            $site->url.
                (str_contains($site->url, '?') ? '&' : '?').
                http_build_query(['username' => $context->player]),
            null,
            3600,
        );
    }

    public function verify(object $site, object $challenge): VoteProof
    {
        $config = $this->configuration($site);
        $query = [
            'object' => 'votes',
            'element' => 'claim',
            'key' => $config['api_key'],
            'username' => $challenge->player_name,
        ];
        $state = trim($this->request('GET', 'https://minecraft-mp.com/api/', $query)->body());
        if ($state === '2' && $challenge->provider_claimed_at) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.verification.claim_uncertain')]);
        }
        $this->assertConfirmed($state === '1');
        Challenge::query()
            ->where('id', $challenge->id)
            ->update(['provider_claimed_at' => now(), 'verification_state' => 'claiming']);
        $claimed = trim($this->request('POST', 'https://minecraft-mp.com/api/', ['action' => 'post'] + $query)->body());
        $this->assertConfirmed($claimed === '1');

        return new VoteProof('mp:'.hash('sha256', strtolower($challenge->player_name).':'.$challenge->id));
    }
}
