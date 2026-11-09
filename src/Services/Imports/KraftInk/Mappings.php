<?php

namespace KraftInk\Plugins\Vote\Services\Imports\KraftInk;

use App\Kraft\Games\GameRegistry;
use App\Kraft\Migration\ImportContext;
use App\Kraft\Migration\MappingRegistry;
use App\Kraft\NavigationTargets;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;
use DateTimeZone;

final class Mappings
{
    public function register(MappingRegistry $registry): void
    {
        $registry->register('kraftink', 'vote__sites', $this->site(...), 55, 'vote', dependencies: ['servers']);
        $registry->register(
            'kraftink',
            'vote__votes',
            $this->vote(...),
            60,
            'vote',
            dependencies: ['users', 'servers', 'vote__sites'],
        );
        $registry->register(
            'kraftink',
            'vote__site_servers',
            function (array $row, ImportContext $context): array {
                $data = [
                    'site_id' => $context->required('vote__sites', $row['site_id']),
                    'server_id' => $context->required('servers', $row['server_id']),
                ];
                $context->target->table('vote__site_servers')->insert($data);

                return ['target_table' => 'vote__site_servers', 'metadata' => $data];
            },
            60,
            'vote',
            dependencies: ['vote__sites', 'servers'],
        );
    }

    private function game(array $row, ImportContext $context): ?string
    {
        $games = app(GameRegistry::class)->all();

        return isset($games[$row['game'] ?? ''])
            ? $row['game']
            : $context->options['game'] ?? app(GameRegistry::class)->activeId();
    }

    private function site(array $row, ImportContext $context): array
    {
        if (
            !is_string($row['url'] ?? null) ||
            !in_array(strtolower(parse_url($row['url'], PHP_URL_SCHEME) ?? ''), ['https', 'http'], true) ||
            !NavigationTargets::validUrl($row['url'])
        ) {
            throw new RuntimeException('Invalid imported vote URL.');
        }
        $reset = $row['reset_time'] ?? null;
        $timezone = $row['reset_timezone'] ?? 'UTC';
        $data = [
            'name' => strip_tags($row['name']),
            'url' => $row['url'],
            'game' => $this->game($row, $context),
            'server_id' => empty($row['server_id']) ? null : $context->required('servers', $row['server_id']),
            'callback_secret' => Crypt::encryptString(Str::random(64)),
            'cooldown_minutes' => max(1, (int) ($row['cooldown_minutes'] ?? 90)),
            'reward_points' => 0,
            'commands' => null,
            'provider' => 'custom',
            'provider_config' => null,
            'requires_online' => (bool) ($row['requires_online'] ?? false),
            'reset_time' => is_string($reset) && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $reset) ? $reset : null,
            'reset_timezone' => in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC',
            'enabled' => false,
            'created_at' => $row['created_at'] ?? now(),
            'updated_at' => $row['updated_at'] ?? now(),
        ];

        return [
            'target_table' => 'vote__sites',
            'target_id' => $context->insert('vote__sites', $data),
            'metadata' => [
                'policy' => 'append_history',
                'archived_fields' => ['callback_secret', 'commands', 'provider_config'],
            ],
        ];
    }

    private function vote(array $row, ImportContext $context): array
    {
        $data = [
            'site_id' => $context->required('vote__sites', $row['site_id']),
            'user_id' => $context->required('users', $row['user_id']),
            'server_id' => empty($row['server_id']) ? null : $context->required('servers', $row['server_id']),
            'game' => $this->game($row, $context),
            'provider_event' => 'import:'.$context->runId.':'.$row['id'],
            'points' => max(0, (int) ($row['points'] ?? 0)),
            'created_at' => $row['created_at'] ?? now(),
        ];

        return [
            'target_table' => 'vote__votes',
            'target_id' => $context->insert('vote__votes', $data),
            'metadata' => ['historical' => true],
        ];
    }
}
