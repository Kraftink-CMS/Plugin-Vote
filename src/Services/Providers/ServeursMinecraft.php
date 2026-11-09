<?php

namespace KraftInk\Plugins\Vote\Services\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use KraftInk\Plugins\Vote\Contracts\VoteProvider;
use KraftInk\Plugins\Vote\Data\ProviderChallenge;
use KraftInk\Plugins\Vote\Data\VoteContext;
use KraftInk\Plugins\Vote\Data\VoteProof;
use Throwable;
use Illuminate\Validation\ValidationException;

class ServeursMinecraft extends HttpProvider implements VoteProvider
{
    public function id(): string
    {
        return 'serveurs-minecraft';
    }

    public function name(): string
    {
        return 'Serveurs-Minecraft.org';
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
        return [
            'timezone' => [
                'type' => 'text',
                'label' => 'vote::messages.verification.response_timezone',
                'rules' => ['required', 'timezone'],
                'default' => 'Europe/Paris',
            ],
        ];
    }

    public function validUrl(string $url): bool
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

        return $this->httpsHost($url, ['serveurs-minecraft.org', 'www.serveurs-minecraft.org']) &&
            parse_url($url, PHP_URL_PATH) === '/vote.php' &&
            isset($query['id']) &&
            is_string($query['id']) &&
            (bool) preg_match('/^[1-9][0-9]{0,8}$/D', $query['id']);
    }

    public function prepare(object $site, VoteContext $context): ProviderChallenge
    {
        return new ProviderChallenge($site->url, null, 600);
    }

    public function verify(object $site, object $challenge): VoteProof
    {
        parse_str(parse_url($site->url, PHP_URL_QUERY) ?? '', $query);
        $this->assertConfirmed($this->validUrl($site->url));
        $serverId = (int) $query['id'];
        $config = $this->configuration($site);
        $ip = Crypt::decryptString($challenge->ip_address);
        $this->assertConfirmed(filter_var($ip, FILTER_VALIDATE_IP) !== false);
        $response = $this->request('GET', 'https://www.serveurs-minecraft.org/api/is_valid_vote.php', [
            'id' => $serverId,
            'ip' => $ip,
            'duration' => 5,
            'format' => 'json',
        ])->json();
        $this->assertConfirmed(
            is_array($response) &&
                empty($response['error']) &&
                (int) ($response['votes'] ?? 0) > 0 &&
                ($response['ip'] ?? '') === $ip &&
                !empty($response['lastVoteDate']),
        );
        try {
            $time = Carbon::parse($response['lastVoteDate'], $config['timezone'])->timestamp;
        } catch (Throwable) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.verification.not_confirmed')]);
        }
        $this->assertConfirmed($time >= strtotime($challenge->created_at) - 60 && $time <= time() + 120);

        return new VoteProof('smorg:'.hash('sha256', $serverId.':'.$ip.':'.$time), $time);
    }
}
