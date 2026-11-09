<?php

namespace KraftInk\Plugins\Vote\Services\Imports\Azuriom;

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
            'azuriom',
            'vote_sites',
            $this->site(...),
            50,
            'vote',
            $this->writer->replacements(),
            guard: $this->writer->assertReplacementReady(...),
        );
        $registry->register(
            'azuriom',
            'vote_votes',
            $this->vote(...),
            60,
            'vote',
            dependencies: ['users', 'vote_sites'],
        );
        new Rewards(new RewardWriter())->register($registry);
    }

    private function site(array $row, ImportContext $context): array
    {
        $data = json_decode((string) ($row['data'] ?? '{}'), true);
        $result = $this->writer->site(
            $context,
            [
                'name' => (string) $row['name'],
                'url' => (string) $row['url'],
                'server_id' => empty($row['server_id']) ? null : $context->mapped('servers', $row['server_id']),
                'allow_guests' => false,
                'ip_mode' => Values::setting($context, 'vote.ipv4-v6-compatibility', true) ? 'any' : 'ipv4',
                'reset_time' => $row['vote_reset_at'] ?? null,
                'reset_timezone' => Values::setting($context, 'timezone', 'UTC'),
                'cooldown_minutes' => $row['vote_delay'] ?? ($row['time'] ?? 90),
                'created_at' => $row['created_at'] ?? ($row['created'] ?? now()),
            ],
            is_array($data) ? $data : [],
            ['table' => 'vote_sites', 'id' => $row['id']],
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
                'table' => 'vote_votes',
                'id' => $row['id'],
                'reason' => 'deleted_vote',
            ]);

            return ['target_table' => null, 'target_id' => null, 'metadata' => ['deleted' => true]];
        }
        $site = $row['site_id'];
        $reward = empty($row['reward_id']) ? null : $context->mapped('vote_rewards', $row['reward_id']);
        $reward = $context->metadata('vote_rewards', $row['reward_id'] ?? null)['sites'][(string) $site] ?? $reward;
        $result = $this->writer->history(
            $context,
            $row['id'],
            [
                'user_id' => empty($row['user_id']) ? null : $context->required('users', $row['user_id']),
                'player' => (string) ($row['username'] ?? ''),
                'guest_last_seen_at' => $row['created'] ?? now(),
                'site_id' => $context->mapped('vote_sites', $site),
                'site_key' => 'vote_sites:'.$site,
                'site_label' => (string) $site,
                'site_origin' => ['table' => 'vote_sites', 'id' => $site],
                'reward_id' => $reward,
                'collected' => true,
                'created_at' => $row['created_at'] ?? ($row['created'] ?? now()),
            ],
            ['table' => 'vote_votes', 'id' => $row['id']],
        );

        return $result;
    }
}
