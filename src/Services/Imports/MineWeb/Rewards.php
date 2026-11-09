<?php

namespace KraftInk\Plugins\Vote\Services\Imports\MineWeb;

use App\Kraft\Migration\ImportContext;
use App\Kraft\Migration\MappingRegistry;
use KraftInk\Plugins\Vote\Services\Imports\RewardWriter;
use KraftInk\Plugins\Vote\Services\Imports\Values;

final class Rewards
{
    public function __construct(private readonly RewardWriter $writer)
    {
    }

    public function register(MappingRegistry $registry): void
    {
        $registry->register('mineweb', 'vote__rewards', $this->reward(...), 55, 'vote');

        $registry->register(
            'mineweb',
            'vote__configurations',
            function (array $row, ImportContext $context): array {
                if (!empty($row['global_command_plural'])) {
                    $context->report('review', [
                        'plugin' => 'vote',
                        'table' => 'vote__configurations',
                        'id' => $row['id'],
                        'reason' => 'bulk_collection_command',
                    ]);
                }

                return [
                    'target_table' => null,
                    'target_id' => null,
                    'metadata' => ['need_register' => (bool) ($row['need_register'] ?? true)],
                ];
            },
            58,
            'vote',
        );
    }

    private function reward(array $row, ImportContext $context): array
    {
        $origin = ['table' => 'vote__rewards', 'id' => $row['id']];
        $sites = [];
        $servers = [];

        foreach ($context->sourceRows('vote__websites') ?? [] as $site) {
            $rewards = Values::array($site['rewards'] ?? []);
            if (
                in_array((string) $row['id'], array_map('strval', $rewards), true) &&
                $context->mapped('vote__websites', $site['id']) !== null
            ) {
                $sites[(string) $site['id']] = $context->required('vote__websites', $site['id']);
                if (!empty($site['server_id'])) {
                    $servers[(string) $site['id']] = array_filter([$context->mapped('servers', $site['server_id'])]);
                }
            }
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
                Values::commands((string) (self::configuration($context)['global_command'] ?? ''), $replacements),
                $origin,
            ),
        );
        $reward = [
            'title' => (string) $row['name'],
            'created_at' => $row['created_at'] ?? ($row['created'] ?? now()),
            'updated_at' => $row['updated_at'] ?? now(),
            'weight' => $this->writer->amount($context, $row['probability'] ?? 0, $origin, 'weight'),
            'wallet_minor' => $this->writer->amount($context, $row['amount'] ?? 0, $origin, 'wallet'),
            'commands' => $commands,
            'requires_online' => (bool) ($row['need_online'] ?? false),
            'player_server_choice' => !empty($row['single_server']),
        ];
        $result = $this->writer->random($context, $reward, $sites, $servers, $origin);

        if (!empty($row['image'])) {
            $context->report('media', ['plugin' => 'vote'] + $origin + ['field' => 'image', 'path' => $row['image']]);
        }
        $result['metadata'] += ['source_enabled' => (bool) ($row['is_enabled'] ?? true), 'servers' => $servers];

        return $result;
    }

    public static function configuration(ImportContext $context): array
    {
        foreach ($context->sourceRows('vote__configurations') ?? [] as $configuration) {
            return $configuration;
        }

        return [];
    }
}
