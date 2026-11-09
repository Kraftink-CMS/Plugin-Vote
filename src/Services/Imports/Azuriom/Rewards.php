<?php

namespace KraftInk\Plugins\Vote\Services\Imports\Azuriom;

use App\Kraft\Migration\ImportContext;
use App\Kraft\Migration\MappingRegistry;
use App\Kraft\Wallet\Wallet;
use KraftInk\Plugins\Vote\Services\Imports\RewardWriter;
use KraftInk\Plugins\Vote\Services\Imports\Values;

final class Rewards
{
    public function __construct(private readonly RewardWriter $writer)
    {
    }

    public function register(MappingRegistry $registry): void
    {
        $registry->register('azuriom', 'vote_rewards', $this->reward(...), 55, 'vote');

        foreach (['vote_reward_site', 'vote_reward_server'] as $table) {
            $registry->register(
                'azuriom',
                $table,
                function (array $row, ImportContext $context) use ($table): array {
                    return [
                        'target_table' => 'vote__rewards',
                        'target_id' => $context->required('vote_rewards', $row['reward_id']),
                        'metadata' => ['relation' => $table],
                    ];
                },
                57,
                'vote',
                identity: '__relation_index',
                dependencies: ['vote_rewards'],
            );
        }
        $registry->settings('azuriom', $this->setting(...), 'vote');
    }

    private function reward(array $row, ImportContext $context): array
    {
        $origin = ['table' => 'vote_rewards', 'id' => $row['id']];
        $sites = [];
        $servers = [];

        foreach ($context->sourceRows('vote_reward_site') ?? [] as $relation) {
            if (
                (string) $relation['reward_id'] === (string) $row['id'] &&
                $context->mapped('vote_sites', $relation['site_id']) !== null
            ) {
                $sites[(string) $relation['site_id']] = $context->required('vote_sites', $relation['site_id']);
            }
        }
        $rewardServers = [];
        foreach ($context->sourceRows('vote_reward_server') ?? [] as $relation) {
            if ((string) $relation['reward_id'] === (string) $row['id']) {
                $server = $context->mapped('servers', $relation['server_id']);
                if ($server !== null) {
                    $rewardServers[] = $server;
                }
            }
        }
        if (empty($rewardServers) && !empty($row['server_id'])) {
            $rewardServers = array_filter([$context->mapped('servers', $row['server_id'])]);
        }
        foreach (array_keys($sites ?: ['' => null]) as $key) {
            $servers[$key] = array_values(array_unique($rewardServers));
        }
        $replacements = [
            '{REWARD_NAME}' => $row['name'],
            '{reward}' => $row['name'],
            '{reward_id}' => (string) $row['id'],
        ];
        $commands = array_merge(
            $this->writer->commands($context, Values::commands($row['commands'] ?? [], $replacements), $origin),
            $this->writer->commands(
                $context,
                Values::commands(Values::setting($context, 'vote.commands', []), $replacements),
                $origin,
            ),
        );
        $reward = [
            'title' => (string) $row['name'],
            'created_at' => $row['created_at'] ?? ($row['created'] ?? now()),
            'updated_at' => $row['updated_at'] ?? now(),
            'weight' => $this->writer->amount($context, $row['chances'] ?? 0, $origin, 'weight'),
            'wallet_minor' => $this->writer->amount($context, $row['money'] ?? 0, $origin, 'wallet'),
            'commands' => $commands,
            'requires_online' => (bool) ($row['need_online'] ?? false),
            'player_server_choice' => !empty($row['single_server']),
        ];
        $result = $this->writer->random($context, $reward, $sites, $servers, $origin);

        $monthlyServers = array_values(array_unique(array_merge(...array_values($servers ?: [[]]))));
        foreach (Values::array($row['monthly_rewards'] ?? []) as $rank) {
            if (!is_numeric($rank) || (int) $rank < 1 || (int) $rank > 1000) {
                $context->report('review', ['plugin' => 'vote'] + $origin + ['reason' => 'invalid_monthly_rank']);

                continue;
            }
            $this->writer->insert($context, $reward, [
                'type' => 'monthly',
                'threshold' => (int) $rank,
                'rank_to' => (int) $rank,
                'server_id' => count($monthlyServers) === 1 ? $monthlyServers[0] : null,
                'wallet_minor' => $reward['wallet_minor'],
                'wallet_currency' => app(Wallet::class)->currency(),
                'commands' => json_encode($commands, JSON_THROW_ON_ERROR),
                'requires_online' => $reward['requires_online'],
            ]);
        }
        if (!empty($row['image'])) {
            $context->report('media', ['plugin' => 'vote'] + $origin + ['field' => 'image', 'path' => $row['image']]);
        }
        $result['metadata'] += ['source_enabled' => (bool) ($row['is_enabled'] ?? true), 'servers' => $servers];

        return $result;
    }

    private function setting(array $setting, ImportContext $context): void
    {
        if (
            $setting['key'] !== 'vote.goal.target' ||
            !is_numeric($setting['value']) ||
            (int) $setting['value'] < 1 ||
            $context->mapped('__vote_goals', 'goal') !== null
        ) {
            return;
        }
        $commands = $this->writer->commands(
            $context,
            Values::commands(Values::setting($context, 'vote.goal.commands', [])),
            ['table' => 'settings', 'id' => 'vote.goal.commands'],
        );
        $id = $this->writer->insert(
            $context,
            ['title' => 'Vote', 'created_at' => now(), 'updated_at' => now()],
            [
                'type' => 'goal',
                'threshold' => min(1000000000, (int) $setting['value']),
                'commands' => json_encode($commands, JSON_THROW_ON_ERROR),
                'period' => 'month',
            ],
        );
        $context->remember('__vote_goals', ['id' => 'goal'], ['target_table' => 'vote__rewards', 'target_id' => $id]);
        $context->report('review', [
            'plugin' => 'vote',
            'table' => 'settings',
            'id' => 'vote.goal.target',
            'reason' => 'goal_schedule_and_servers',
        ]);
    }
}
