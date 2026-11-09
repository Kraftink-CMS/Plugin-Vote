<?php

namespace KraftInk\Plugins\Vote\Services\Imports\MineWeb;

use App\Kraft\Migration\ImportContext;
use App\Kraft\Migration\MappingRegistry;
use KraftInk\Plugins\Vote\Services\Imports\RewardWriter;
use KraftInk\Plugins\Vote\Services\Imports\Values;
use KraftInk\Plugins\Vote\Services\Imports\Writer;

final class Mappings
{
    public function __construct(private readonly Writer $writer)
    {
    }

    public function register(MappingRegistry $registry): void
    {
        $registry->register(
            'mineweb',
            'vote__websites',
            $this->site(...),
            50,
            'vote',
            $this->writer->replacements(),
            guard: $this->writer->assertReplacementReady(...),
        );
        $registry->register(
            'mineweb',
            'vote__votes',
            $this->vote(...),
            60,
            'vote',
            dependencies: ['users', 'vote__websites'],
        );
        new Rewards(new RewardWriter())->register($registry);
    }

    private function site(array $row, ImportContext $context): array
    {
        $data = json_decode((string) ($row['data'] ?? '{}'), true);
        $configuration = Rewards::configuration($context);
        $result = $this->writer->site(
            $context,
            [
                'name' => (string) $row['name'],
                'url' => (string) $row['url'],
                'server_id' => empty($row['server_id']) ? null : $context->mapped('servers', $row['server_id']),
                'allow_guests' => array_key_exists('need_register', $configuration) && !$configuration['need_register'],
                'ip_mode' => Values::setting($context, 'vote.ipv4-v6-compatibility', true) ? 'any' : 'ipv4',
                'reset_time' => $row['vote_reset_at'] ?? null,
                'reset_timezone' => Values::setting($context, 'timezone', 'UTC'),
                'cooldown_minutes' => $row['vote_delay'] ?? ($row['time'] ?? 90),
                'created_at' => $row['created_at'] ?? ($row['created'] ?? now()),
            ],
            is_array($data) ? $data : [],
            ['table' => 'vote__websites', 'id' => $row['id']],
        );

        return $result + [
            'metadata' => [
                'source_server_id' => $row['server_id'] ?? null,
                'source_enabled' => (bool) ($row['is_enabled'] ?? true),
            ],
        ];
    }

    private function vote(array $row, ImportContext $context): array
    {
        if (!empty($row['deleted_at'])) {
            $context->report('archive', [
                'plugin' => 'vote',
                'table' => 'vote__votes',
                'id' => $row['id'],
                'reason' => 'deleted_vote',
            ]);

            return ['target_table' => null, 'target_id' => null, 'metadata' => ['deleted' => true]];
        }
        $site = $row['website_id'];
        $reward = empty($row['reward_id']) ? null : $context->mapped('vote__rewards', $row['reward_id']);
        $reward = $context->metadata('vote__rewards', $row['reward_id'] ?? null)['sites'][(string) $site] ?? $reward;
        $result = $this->writer->history(
            $context,
            $row['id'],
            [
                'user_id' => empty($row['user_id']) ? null : $context->required('users', $row['user_id']),
                'player' => (string) ($row['username'] ?? ''),
                'guest_last_seen_at' => $row['created'] ?? now(),
                'site_id' => $context->mapped('vote__websites', $site),
                'site_key' => 'vote__websites:'.$site,
                'site_label' => (string) $site,
                'site_origin' => ['table' => 'vote__websites', 'id' => $site],
                'reward_id' => $reward,
                'collected' => !empty($row['collected']),
                'created_at' => $row['created_at'] ?? ($row['created'] ?? now()),
            ],
            ['table' => 'vote__votes', 'id' => $row['id']],
        );

        if ($result['target_id'] !== null && empty($row['collected']) && !empty($row['reward_id'])) {
            $context->report('review', [
                'plugin' => 'vote',
                'table' => 'vote__votes',
                'id' => $row['id'],
                'reason' => 'pending_reward_not_replayed',
            ]);
        }

        return $result;
    }
}
