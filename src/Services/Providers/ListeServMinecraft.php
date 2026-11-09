<?php

namespace KraftInk\Plugins\Vote\Services\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use KraftInk\Plugins\Vote\Data\VoteProof;
use Throwable;

class ListeServMinecraft extends ServerMinecraft
{
    public function id(): string
    {
        return 'liste-serv-minecraft';
    }

    public function name(): string
    {
        return 'Liste-serv-minecraft.fr';
    }

    protected function apiHosts(): array
    {
        return ['liste-serv-minecraft.fr'];
    }

    public function fields(): array
    {
        return [
            'server_id' => [
                'type' => 'text',
                'label' => 'vote::messages.verification.server_id',
                'rules' => ['required', 'integer', 'min:1'],
            ],
            'response_timezone' => [
                'type' => 'text',
                'label' => 'vote::messages.verification.response_timezone',
                'default' => 'Europe/Paris',
                'rules' => ['required', 'timezone'],
            ],
        ];
    }

    public function validUrl(string $url): bool
    {
        return $this->httpsHost($url, ['liste-serv-minecraft.fr', 'www.liste-serv-minecraft.fr']);
    }

    public function verify(object $site, object $challenge): VoteProof
    {
        $config = $this->configuration($site);
        $ip = Crypt::decryptString($challenge->ip_address);
        $this->assertConfirmed(filter_var($ip, FILTER_VALIDATE_IP) !== false);
        $data = $this->request('GET', 'https://liste-serv-minecraft.fr/api/check', [
            'server' => $config['server_id'],
            'ip' => $ip,
            'username' => $challenge->player_name,
            'method' => 'AND',
        ])->json();
        $this->assertConfirmed(
            ($data['status'] ?? null) === 200 &&
                is_numeric($data['id_vote'] ?? null) &&
                (int) $data['id_vote'] > 0 &&
                is_string($data['username'] ?? null) &&
                strcasecmp($data['username'], $challenge->player_name) === 0 &&
                is_string($data['datetime_vote_start'] ?? null),
        );
        try {
            $time = CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $data['datetime_vote_start'],
                $config['response_timezone'],
            )->timestamp;
        } catch (Throwable) {
            $time = 0;
        }
        $this->assertConfirmed($time >= strtotime($challenge->created_at) - 60 && $time <= time() + 120);

        return new VoteProof('lsm:'.$data['id_vote'], $time);
    }
}
