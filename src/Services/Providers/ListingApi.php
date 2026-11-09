<?php

namespace KraftInk\Plugins\Vote\Services\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use KraftInk\Plugins\Vote\Contracts\VoteProvider;
use KraftInk\Plugins\Vote\Data\ProviderChallenge;
use KraftInk\Plugins\Vote\Data\VoteContext;
use KraftInk\Plugins\Vote\Data\VoteProof;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;
use KraftInk\Plugins\Vote\Models\Voting\Vote;
use InvalidArgumentException;
use Throwable;

class ListingApi extends HttpProvider implements VoteProvider
{
    public const array SITES = [
        'serveurliste' => ['serveurliste.com', 'ServeurListe', [], 90, ['api_key']],
        'serveur-minecraft-vote' => [
            'serveur-minecraft-vote.fr',
            'Serveur Minecraft Vote',
            ['minecraft'],
            180,
            ['server_id'],
        ],
        'serveursminecraft' => ['serveursminecraft.org', 'ServeursMinecraft.org', ['minecraft'], 180, ['server_id']],
        'liste-serveurs' => ['liste-serveurs.fr', 'Liste-Serveurs.fr', [], 1440, ['server_id']],
        'minecraft-italia' => ['minecraft-italia.net', 'Minecraft Italia', ['minecraft'], 1440, ['server_id']],
        'gamemonitoring' => ['gamemonitoring.ru', 'GameMonitoring', [], 1440, ['server_id']],
        'minecraft-fr' => ['minecraft.fr', 'Minecraft.fr', ['minecraft'], 1440, ['api_key']],
        'minecraft-serveurs' => ['minecraft-serveurs.com', 'Minecraft-Serveurs', ['minecraft'], 180, ['api_key']],
        'discordtop' => ['discordtop.net', 'DiscordTop', [], 60, ['api_key']],
        'hytale-servers' => ['hytale-servers.best', 'Hytale Servers', ['hytale'], 180, ['server_id']],
        'trackyserver' => [
            'trackyserver.com',
            'TrackyServer',
            [
                'garrysmod',
                'rust',
                'fivem',
                'dayz',
                'ark',
                'sevendaystodie',
                'counterstrike2',
                'teamfortress2',
                'unturned',
                'projectzomboid',
            ],
            1440,
            ['api_key'],
        ],
        'serveurly' => ['serveurly.com', 'Serveurly', [], 90, ['api_key']],
        'top-serveurs' => ['top-serveurs.net', 'Top-Serveurs', [], 120, ['api_key']],
        'top-games' => ['top-games.net', 'Top-Games', [], 120, ['api_key']],
        'liste-serveurs-minecraft' => [
            'liste-serveurs-minecraft.org',
            'Liste Serveurs Minecraft',
            ['minecraft'],
            180,
            ['server_id'],
        ],
        'minecraft-server-net' => ['minecraft-server.net', 'Minecraft Server', ['minecraft'], 1440, ['api_key']],
        'playbase' => ['playbase.pro', 'PlayBase', [], 1440, ['api_key', 'server_id']],
        'playerpicked' => ['playerpicked.com', 'PlayerPicked', [], 1440, ['server_id']],
        'voxelrank' => ['voxelrank.com', 'VoxelRank', [], 1440, ['api_key']],
    ];

    private array $definition;

    public function __construct(private readonly string $identifier)
    {
        $this->definition = self::SITES[$identifier] ?? throw new InvalidArgumentException('Unknown vote listing.');
    }

    public function id(): string
    {
        return $this->identifier;
    }

    public function name(): string
    {
        return $this->definition[1];
    }

    public function games(): array
    {
        return $this->definition[2];
    }

    public function mode(): string
    {
        return 'poll';
    }

    public function minimumCooldown(): int
    {
        return $this->definition[3];
    }

    public function fields(): array
    {
        $fields = [];
        foreach ($this->definition[4] as $field) {
            $fields[$field] = [
                'type' => $field === 'api_key' ? 'secret' : 'text',
                'label' => 'vote::messages.verification.'.$field,
                'rules' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/D'],
            ];
        }

        return $fields;
    }

    public function validUrl(string $url): bool
    {
        return $this->httpsHost(explode('#', $url, 2)[0], [$this->definition[0], 'www.'.$this->definition[0]]);
    }

    protected function apiHosts(): array
    {
        return [$this->definition[0], 'www.'.$this->definition[0], 'api.'.$this->definition[0]];
    }

    public function prepare(object $site, VoteContext $context): ProviderChallenge
    {
        $this->assertConfirmed($this->validUrl($site->url));
        $parameters = match ($this->identifier) {
            'discordtop' => ['external_id' => $context->player],
            'serveurliste' => ['name' => $context->player],
            default => ['username' => $context->player],
        };
        [$url, $fragment] = array_pad(explode('#', $site->url, 2), 2, '');

        return new ProviderChallenge(
            $url.
                (str_contains($url, '?') ? '&' : '?').
                http_build_query($parameters).
                ($fragment === '' ? '' : '#'.$fragment),
        );
    }

    public function verify(object $site, object $challenge): VoteProof
    {
        $config = $this->configuration($site);
        $ip = Crypt::decryptString($challenge->ip_address);
        $this->assertConfirmed(filter_var($ip, FILTER_VALIDATE_IP) !== false);
        $name = $challenge->player_name;
        $this->assertConfirmed(is_string($name) && preg_match('/^[\pL\pN_ .:+-]{1,60}$/uD', $name) === 1);
        $key = $config['api_key'] ?? '';
        $server = $config['server_id'] ?? '';
        $time = null;
        $event = null;
        switch ($this->identifier) {
            case 'trackyserver':
                $steam = $challenge->player_uuid;
                $this->assertConfirmed($this->steamIdentity($steam));
                $parameters = ['key' => $key, 'steamid' => $steam];
                $status = trim(
                    $this->request(
                        'GET',
                        'https://api.trackyserver.com/vote/',
                        ['action' => 'status'] + $parameters,
                    )->body(),
                );
                $this->assertConfirmed($status === '1' && !$challenge->provider_claimed_at);
                $this->claiming($challenge);
                $this->assertConfirmed(
                    trim(
                        $this->request(
                            'GET',
                            'https://api.trackyserver.com/vote/',
                            ['action' => 'claim'] + $parameters,
                        )->body(),
                    ) === '1',
                );
                $event = $challenge->id;
                break;
            case 'voxelrank':
                $parameters = $this->steamIdentity($challenge->player_uuid ?? null)
                    ? ['steamid' => $challenge->player_uuid]
                    : ['username' => $name];
                $headers = ['X-Api-Key' => $key];
                $data = $this->request(
                    'GET',
                    'https://voxelrank.com/api/v1/votes/check',
                    $parameters,
                    $headers,
                )->json();
                $this->assertConfirmed(($data['voted_recently'] ?? null) === true && !$challenge->provider_claimed_at);
                $time = $this->recent($data['last_vote'] ?? null, $challenge);
                $this->claiming($challenge);
                $claimed = $this->request(
                    'POST',
                    'https://voxelrank.com/api/v1/votes/claim',
                    [],
                    $headers,
                    $parameters,
                )->json();
                $this->assertConfirmed(
                    is_int($claimed['claimed'] ?? null) &&
                        $claimed['claimed'] > 0 &&
                        $claimed['claimed'] <= 1000 &&
                        is_array($claimed['ids'] ?? null) &&
                        array_is_list($claimed['ids']) &&
                        count($claimed['ids']) === $claimed['claimed'],
                );
                $ids = [];
                foreach ($claimed['ids'] as $id) {
                    $this->assertConfirmed(
                        (is_int($id) || is_string($id)) && preg_match('/^[1-9][0-9]{0,19}$/D', (string) $id) === 1,
                    );
                    $ids[] = (string) $id;
                }
                $this->assertConfirmed(count(array_unique($ids)) === count($ids));
                sort($ids, SORT_STRING);
                $event = implode(',', $ids);
                break;
            case 'serveurly':
                $headers = ['Authorization' => 'Bearer '.$key];
                $parameters = ['username' => $name];
                $data = $this->request(
                    'GET',
                    'https://serveurly.com/api/v1/votes/check',
                    $parameters,
                    $headers,
                )->json();
                $this->assertConfirmed(
                    ($data['success'] ?? null) === true &&
                        ($data['data']['has_voted'] ?? null) === true &&
                        !$challenge->provider_claimed_at,
                );
                $this->claiming($challenge);
                $claimed = $this->request(
                    'POST',
                    'https://serveurly.com/api/v1/votes/claim',
                    [],
                    $headers,
                    $parameters,
                )->json();
                $this->assertConfirmed(($claimed['success'] ?? null) === true && empty($claimed['error']));
                $event = $claimed['data']['vote_id'] ?? ($claimed['data']['id'] ?? $challenge->id);
                break;
            case 'minecraft-server-net':
                $this->assertConfirmed(!$challenge->provider_claimed_at);
                $this->claiming($challenge);
                $state = trim(
                    $this->request(
                        'POST',
                        'https://minecraft-server.net/api/',
                        [],
                        [],
                        ['action' => 'checkVote', 'key' => $key, 'player' => $name],
                        true,
                    )->body(),
                );
                if ($state === '0') {
                    Challenge::query()
                        ->where('id', $challenge->id)
                        ->whereNull('used_at')
                        ->where('verification_state', 'claiming')
                        ->update(['provider_claimed_at' => null, 'verification_state' => 'pending']);
                }
                $this->assertConfirmed($state === '1');
                $event = $challenge->id;
                break;
            case 'serveurliste':
                $data = $this->request('GET', 'https://serveurliste.com/api/vote', [
                    'ip_address' => $ip,
                    'api_token' => $key,
                ])->json();
                $this->assertConfirmed(($data['data']['voted'] ?? null) === true);
                $time = $this->optionalRecent(
                    $data['data']['last_vote'] ??
                        ($data['data']['last_vote_date'] ?? ($data['data']['voted_at'] ?? null)),
                    $challenge,
                );
                break;
            case 'serveur-minecraft-vote':
                $data = $this->request(
                    'GET',
                    'https://serveur-minecraft-vote.fr/api/v1/servers/'.
                        rawurlencode($server).
                        '/vote/'.
                        rawurlencode($ip),
                )->json();
                $this->assertConfirmed(($data['canVote'] ?? null) === false);
                $time = $this->optionalRecent($data['lastVote'] ?? ($data['last_vote'] ?? null), $challenge);
                break;
            case 'serveursminecraft':
                $remaining = trim(
                    $this->request('GET', 'https://www.serveursminecraft.org/sm_api/peutVoter.php', [
                        'id' => $server,
                        'ip' => $ip,
                    ])->body(),
                );
                $this->assertConfirmed(ctype_digit($remaining) && (int) $remaining > 0 && (int) $remaining <= 10800);
                break;
            case 'liste-serveurs':
                $data = $this->request(
                    'GET',
                    'https://www.liste-serveurs.fr/api/checkVote/'.rawurlencode($server).'/'.rawurlencode($ip),
                )->json();
                $this->assertConfirmed(($data['success'] ?? null) === true);
                $time = $this->optionalRecent($data['voted_at'] ?? ($data['last_vote'] ?? null), $challenge);
                break;
            case 'minecraft-italia':
                $data = $this->request('GET', 'https://minecraft-italia.net/lista/api/vote/server', [
                    'serverId' => $server,
                ])->json();
                $entry = collect(is_array($data) ? $data : [])->first(
                    fn ($entry) => is_array($entry) && strcasecmp((string) ($entry['username'] ?? ''), $name) === 0,
                );
                $this->assertConfirmed(is_array($entry));
                $time = $this->recent($entry['timestamp'] ?? ($entry['voted_at'] ?? null), $challenge);
                break;
            case 'gamemonitoring':
                $data = $this->request('GET', 'https://api.gamemonitoring.ru/votes', ['server_id' => $server])->json();
                $entry = collect($data['response']['items'] ?? [])->first(
                    fn ($entry) => is_array($entry) && strcasecmp((string) ($entry['nickname'] ?? ''), $name) === 0,
                );
                $this->assertConfirmed(is_array($entry));
                $time = $this->recent($entry['timestamp'] ?? ($entry['created_at'] ?? null), $challenge);
                break;
            case 'minecraft-fr':
                $data = $this->request(
                    'GET',
                    'https://minecraft.fr/wp-json/serveursmc/v1/votes/'.rawurlencode($name),
                    ['api_key' => $key],
                )->json();
                $this->assertConfirmed(($data['has_voted'] ?? null) === true);
                $time = $this->optionalRecent($data['voted_at'] ?? ($data['last_vote'] ?? null), $challenge);
                break;
            case 'minecraft-serveurs':
                $this->claiming($challenge, true);
                $data = $this->request(
                    'GET',
                    'https://minecraft-serveurs.com/api/v1/vote-check',
                    ['ip' => $ip, 'name' => $name],
                    ['Authorization' => 'Bearer '.$key],
                )->json();
                $this->assertConfirmed(
                    ($data['voted'] ?? null) === true &&
                        (is_int($data['vote']['id'] ?? null) || is_string($data['vote']['id'] ?? null)) &&
                        preg_match('/^[A-Za-z0-9_-]{1,140}$/D', (string) $data['vote']['id']) === 1 &&
                        is_string($data['vote']['nickname'] ?? null) &&
                        strcasecmp($data['vote']['nickname'], $name) === 0 &&
                        !empty($data['claim']['id']),
                );
                $time = $this->recent($data['vote']['createdAt'] ?? null, $challenge);
                $event = $data['vote']['id'];
                break;
            case 'discordtop':
                $data = $this->request(
                    'GET',
                    'https://api.discordtop.net/v7/vote-check',
                    ['external_id' => $name],
                    ['Authorization' => 'Bearer '.$key],
                )->json();
                $this->assertConfirmed(($data['has_voted'] ?? null) === true);
                $time = $this->optionalRecent($data['last_vote_at'] ?? ($data['voted_at'] ?? null), $challenge);
                break;
            case 'hytale-servers':
                $this->assertConfirmed(
                    trim(
                        $this->request('GET', 'https://hytale-servers.best/api/vote-verification', [
                            'server_id' => $server,
                            'ip' => $ip,
                            'duration' => 180,
                        ])->body(),
                    ) === '1',
                );
                break;
            case 'top-serveurs':
            case 'top-games':
                $data = $this->request('GET', 'https://api.'.$this->definition[0].'/v1/votes/check-ip', [
                    'server_token' => $key,
                    'ip' => $ip,
                ])->json();
                $this->assertConfirmed(($data['code'] ?? null) === 200);
                $time = $this->optionalRecent($data['voted_at'] ?? ($data['last_vote'] ?? null), $challenge);
                break;
            case 'liste-serveurs-minecraft':
                $this->assertConfirmed(
                    trim(
                        $this->request('GET', 'https://api.liste-serveurs-minecraft.org/vote/vote_verification.php', [
                            'server_id' => $server,
                            'ip' => $ip,
                            'duration' => 5,
                        ])->body(),
                    ) === '1',
                );
                break;
            case 'playbase':
                $data = $this->request(
                    'GET',
                    'https://playbase.pro/api/vote/'.rawurlencode($server).'/'.rawurlencode($ip),
                    [],
                    ['Authorization' => 'Bearer '.$key],
                )->json();
                $time = $this->recent($data['date'] ?? null, $challenge);
                break;
            case 'playerpicked':
                $data = $this->request('GET', 'https://playerpicked.com/api/vote/check', [
                    'server' => $server,
                    'ip' => $ip,
                    'name' => $name,
                ])->json();
                $this->assertConfirmed(($data['has_voted'] ?? null) === true);
                $time = $this->optionalRecent($data['voted_at'] ?? ($data['last_vote'] ?? null), $challenge);
                break;
            default:
                $this->assertConfirmed(false);
        }
        if ($event !== null) {
            $this->assertConfirmed(is_int($event) || is_string($event));
            $this->assertConfirmed((string) $event !== '' && strlen((string) $event) <= 21000);

            return new VoteProof($this->identifier.':'.hash('sha256', $site->id.':native:'.$event), $time);
        }
        $prefix = $this->identifier.':'.hash('sha256', $site->id.':'.$this->correlation($challenge)).':';
        $latest = Vote::query()
            ->where('site_id', $site->id)
            ->where('provider_event', 'like', $prefix.'%')
            ->where('created_at', '>', now()->subMinutes(max($this->minimumCooldown(), $site->cooldown_minutes)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('provider_event');

        return new VoteProof($latest ?? $prefix.hash('sha256', (string) ($time ?? $challenge->id)), $time);
    }

    public function correlation(object $challenge): string
    {
        if (
            in_array(
                $this->identifier,
                [
                    'serveurliste',
                    'serveur-minecraft-vote',
                    'serveursminecraft',
                    'liste-serveurs',
                    'hytale-servers',
                    'top-serveurs',
                    'top-games',
                    'liste-serveurs-minecraft',
                    'playbase',
                    'playerpicked',
                ],
                true,
            )
        ) {
            $ip = Crypt::decryptString($challenge->ip_address);
            $this->assertConfirmed(filter_var($ip, FILTER_VALIDATE_IP) !== false);

            return 'ip:'.hash_hmac('sha256', inet_pton($ip), config('app.key'));
        }
        if (
            in_array($this->identifier, ['trackyserver', 'voxelrank'], true) &&
            $this->steamIdentity($challenge->player_uuid ?? null)
        ) {
            return 'steam:'.$challenge->player_uuid;
        }

        return 'name:'.strtolower($challenge->player_name);
    }

    private function steamIdentity(mixed $identity): bool
    {
        return is_string($identity) &&
            preg_match('/^[0-9]{17}$/D', $identity) === 1 &&
            strcmp($identity, '76561197960265728') >= 0 &&
            strcmp($identity, '76561202255233023') <= 0;
    }

    private function claiming(object $challenge, bool $repeatable = false): void
    {
        $updated = Challenge::query()
            ->where('id', $challenge->id)
            ->whereNull('used_at')
            ->when(!$repeatable, fn ($query) => $query->whereNull('provider_claimed_at'))
            ->update(['provider_claimed_at' => now(), 'verification_state' => 'claiming']);
        $this->assertConfirmed($updated === 1);
    }

    private function optionalRecent(mixed $value, object $challenge): ?int
    {
        return $value === null ? null : $this->recent($value, $challenge);
    }

    private function recent(mixed $value, object $challenge): int
    {
        $this->assertConfirmed(is_int($value) || is_string($value));
        if (is_string($value) && !ctype_digit($value)) {
            $this->assertConfirmed(
                preg_match(
                    '/^[0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?(?:Z|[+-][0-9]{2}:?[0-9]{2})?$/D',
                    $value,
                ) === 1,
            );
        }
        try {
            $time = is_numeric($value) ? (int) $value : CarbonImmutable::parse($value, 'UTC')->timestamp;
        } catch (Throwable) {
            $time = 0;
        }
        $this->assertConfirmed($time >= strtotime($challenge->created_at) - 60 && $time <= time() + 120);

        return $time;
    }
}
