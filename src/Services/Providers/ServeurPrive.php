<?php

namespace KraftInk\Plugins\Vote\Services\Providers;

use Illuminate\Support\Facades\Crypt;
use KraftInk\Plugins\Vote\Contracts\VoteProvider;
use KraftInk\Plugins\Vote\Data\ProviderChallenge;
use KraftInk\Plugins\Vote\Data\VoteContext;
use KraftInk\Plugins\Vote\Data\VoteProof;
use KraftInk\Plugins\Vote\Services\ChallengePayload;

class ServeurPrive extends HttpProvider implements VoteProvider
{
    public function id(): string
    {
        return 'serveur-prive';
    }

    public function name(): string
    {
        return 'Serveur Privé';
    }

    public function games(): array
    {
        return [];
    }

    public function mode(): string
    {
        return 'poll';
    }

    public function minimumCooldown(): int
    {
        return 90;
    }

    public function fields(): array
    {
        return [
            'server_token' => [
                'type' => 'secret',
                'label' => 'vote::messages.verification.server_token',
                'rules' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{8,200}$/D'],
            ],
        ];
    }

    public function validUrl(string $url): bool
    {
        return $this->httpsHost($url, ['serveur-prive.net', 'www.serveur-prive.net']) &&
            (bool) preg_match('~^/[a-z0-9-]+/[a-z0-9-]+/vote/?$~D', parse_url($url, PHP_URL_PATH) ?? '') &&
            !parse_url($url, PHP_URL_QUERY);
    }

    public function prepare(object $site, VoteContext $context): ProviderChallenge
    {
        $config = $this->configuration($site);
        $serverUrl = 'https://serveur-prive.net/api/v1/servers/'.rawurlencode($config['server_token']);
        $details = $this->request('GET', $serverUrl)->json();
        $useOtp = $details['data']['use_otp'] ?? null;
        $voteUrl = $details['data']['urls']['vote_page'] ?? null;
        $this->assertConfirmed(
            ($details['success'] ?? false) === true &&
                is_bool($useOtp) &&
                is_string($voteUrl) &&
                $this->validUrl($voteUrl) &&
                rtrim(parse_url($voteUrl, PHP_URL_PATH), '/') === rtrim(parse_url($site->url, PHP_URL_PATH), '/'),
        );
        $url = rtrim($site->url, '/').'/'.rawurlencode($context->player);
        if (!$useOtp) {
            return new ProviderChallenge($url);
        }
        $response = $this->request('GET', $serverUrl.'/otp')->json();
        $token = $response['data']['token'] ?? null;
        $this->assertConfirmed(
            ($response['success'] ?? false) === true &&
                is_string($token) &&
                (bool) preg_match('/^[a-zA-Z0-9_-]{8,200}$/D', $token),
        );

        return new ProviderChallenge($url.'?'.http_build_query(['token' => $token]), $token, 300, $token);
    }

    public function verify(object $site, object $challenge): VoteProof
    {
        $config = $this->configuration($site);
        $token = ChallengePayload::token($challenge);
        $ip = $token === null ? Crypt::decryptString($challenge->ip_address) : null;
        $this->assertConfirmed($token !== null || filter_var($ip, FILTER_VALIDATE_IP) !== false);
        $response = $this->request(
            'GET',
            'https://serveur-prive.net/api/v1/servers/'.
                rawurlencode($config['server_token']).
                '/votes/'.
                ($token !== null ? 'otp/'.rawurlencode($token) : rawurlencode($ip)),
        )->json();
        $time = $response['data']['voted_at'] ?? null;
        $this->assertConfirmed(
            ($response['success'] ?? false) === true &&
                is_int($time) &&
                $time > 0 &&
                $time <= time() + 120 &&
                $time >= strtotime($challenge->created_at) - 60,
        );
        if ($token === null) {
            $matchedIp = false;
            foreach (['ipv4', 'ipv6'] as $field) {
                $address = $response['data'][$field] ?? null;
                $matchedIp =
                    $matchedIp ||
                    (is_string($address) &&
                        filter_var($address, FILTER_VALIDATE_IP) !== false &&
                        inet_pton($address) === inet_pton($ip));
            }
            $username = $response['data']['username'] ?? null;
            $this->assertConfirmed(
                $matchedIp && is_string($username) && strcasecmp($username, $challenge->player_name) === 0,
            );
        }

        return new VoteProof('sp:'.hash('sha256', $token ?? 'ip:'.bin2hex(inet_pton($ip)).':'.$time), $time);
    }
}
