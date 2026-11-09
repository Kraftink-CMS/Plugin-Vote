<?php

namespace KraftInk\Plugins\Vote\Services\Imports;

use App\Kraft\Games\GameRegistry;
use App\Kraft\Migration\ImportContext;
use App\Kraft\Wallet\Money;
use App\Kraft\Wallet\Wallet;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class RewardWriter
{
    public function random(ImportContext $context, array $reward, array $sites, array $servers, array $origin): array
    {
        $variants = [];
        $primary = null;
        foreach ($sites ?: ['' => null] as $key => $site) {
            $serverIds = $servers[$key] ?? [];
            $id = $this->insert($context, $reward, [
                'site_id' => $site,
                'server_id' => count($serverIds) === 1 ? reset($serverIds) : null,
                'weight' => max(1, min(1000000, $reward['weight'])),
                'chance_bps' => $reward['weight'] > 0 ? 10000 : 0,
                'wallet_minor' => $reward['wallet_minor'],
                'wallet_currency' => app(Wallet::class)->currency(),
                'commands' => json_encode($reward['commands'], JSON_THROW_ON_ERROR),
                'pool' => 'import-'.substr($context->runId, 0, 8).'-'.$key,
                'requires_online' => $reward['requires_online'],
            ]);
            $primary ??= $id;
            $variants[$key] = $id;
            if ((count($serverIds) !== 1 || $reward['player_server_choice']) && $reward['commands'] !== []) {
                $context->report(
                    'review',
                    ['plugin' => 'vote'] +
                        $origin + [
                            'reason' => $reward['player_server_choice']
                                ? 'player_server_choice'
                                : 'reward_server_assignment',
                        ],
                );
            }
        }
        if (!$sites) {
            $context->report('review', ['plugin' => 'vote'] + $origin + ['reason' => 'unassigned_reward']);
        }

        return ['target_table' => 'vote__rewards', 'target_id' => $primary, 'metadata' => ['sites' => $variants]];
    }

    public function insert(ImportContext $context, array $reward, array $data): int
    {
        return $context->insert(
            'vote__rewards',
            $data + [
                'titles' => json_encode(
                    [config('imports.locale') => mb_substr(strip_tags($reward['title']), 0, 160)],
                    JSON_THROW_ON_ERROR,
                ),
                'type' => 'random',
                'permission' => 'imports.review',
                'enabled' => false,
                'game' => $context->options['game'] ?? app(GameRegistry::class)->activeId(),
                'created_at' => $reward['created_at'],
                'updated_at' => $reward['updated_at'],
            ],
        );
    }

    public function commands(ImportContext $context, array $values, array $origin): array
    {
        $commands = [];
        foreach ($values as $command) {
            $command = trim($command);
            if ($command === '' || strlen($command) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $command)) {
                $context->report('review', ['plugin' => 'vote'] + $origin + ['reason' => 'invalid_command']);

                continue;
            }
            if (preg_match('/\{(?!player\}|uuid\})[^}]+\}/', $command)) {
                $context->report(
                    'review',
                    ['plugin' => 'vote'] + $origin + ['reason' => 'unsupported_command_placeholder'],
                );
            }
            $commands[] = $command;
        }

        return $commands;
    }

    public function amount(ImportContext $context, mixed $value, array $origin, string $field): int
    {
        try {
            $amount = Money::parse((string) ($value ?? 0));
            if ($amount > ($field === 'weight' ? 1000000 : 1000000000)) {
                throw new RuntimeException('Imported reward value exceeds supported bounds.');
            }

            return $amount;
        } catch (ValidationException|RuntimeException) {
            $context->report('review', ['plugin' => 'vote'] + $origin + ['reason' => 'invalid_reward_'.$field]);

            return 0;
        }
    }
}
