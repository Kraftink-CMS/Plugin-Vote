<?php

namespace KraftInk\Plugins\Vote\Services\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use KraftInk\Plugins\Vote\Contracts\VoteProvider;
use KraftInk\Plugins\Vote\Data\ProviderChallenge;
use KraftInk\Plugins\Vote\Data\VoteContext;
use KraftInk\Plugins\Vote\Data\VoteProof;
use Throwable;

class ServerMinecraft extends HttpProvider implements VoteProvider
{
    public function id(): string
    {
        return 'serveur-minecraft-com';
    }

    public function name(): string
    {
        return 'Serveur-Minecraft.com';
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
        return 180;
    }

    public function fields(): array
    {
        return [];
    }

    protected function apiHosts(): array
    {
        return ['serveur-minecraft.com'];
    }

    public function validUrl(string $url): bool
    {
        return $this->httpsHost($url, ['serveur-minecraft.com', 'www.serveur-minecraft.com']) &&
            preg_match('~^/[0-9]+(?:/vote)?/?$~D', parse_url($url, PHP_URL_PATH) ?? '') === 1;
    }

    public function prepare(object $site, VoteContext $context): ProviderChallenge
    {
        return new ProviderChallenge($site->url);
    }

    public function verify(object $site, object $challenge): VoteProof
    {
        $this->assertConfirmed($this->validUrl($site->url));
        preg_match('~^/([0-9]+)~', parse_url($site->url, PHP_URL_PATH), $match);
        $ip = Crypt::decryptString($challenge->ip_address);
        $this->assertConfirmed(filter_var($ip, FILTER_VALIDATE_IP) !== false);
        $data = $this->request(
            'GET',
            'https://serveur-minecraft.com/api/1/vote/'.$match[1].'/'.rawurlencode($ip).'/json',
        )->json();
        $this->assertConfirmed(in_array($data['vote'] ?? null, [1, '1'], true) && is_string($data['voted_at'] ?? null));
        try {
            $time = CarbonImmutable::parse($data['voted_at'])->timestamp;
        } catch (Throwable) {
            $time = 0;
        }
        $this->assertConfirmed($time >= strtotime($challenge->created_at) - 60 && $time <= time() + 120);

        return new VoteProof('smc:'.hash('sha256', $ip.':'.$time), $time);
    }
}
