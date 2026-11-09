<?php

namespace KraftInk\Plugins\Vote\Services\Imports;

use App\Kraft\Games\GameRegistry;
use App\Kraft\Migration\ImportContext;
use App\Kraft\NavigationTargets;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Services\Providers\ProviderRegistry;
use KraftInk\Plugins\Vote\Services\VerificationLock;
use RuntimeException;
use DateTimeZone;
use Illuminate\Cache\DatabaseStore;
use LogicException;

final class Writer
{
    public function replacements(): array
    {
        return [
            'notification_deliveries' => ['owner' => ['vote']],
            'vote__reviews' => [],
            'vote__deliveries' => [],
            'vote__role_grants' => [],
            'vote__reward_claims' => [],
            'vote__reward_runs' => [],
            'vote__rewards' => [],
            'vote__challenges' => [],
            'vote__votes' => [],
            'vote__site_servers' => [],
            'vote__sites' => [],
        ];
    }

    public function site(ImportContext $context, array $site, array $providerData, array $origin): array
    {
        $url = $site['url'];
        if (
            !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['https', 'http'], true) ||
            !NavigationTargets::validUrl($url)
        ) {
            throw new RuntimeException('Invalid imported vote URL.');
        }
        $provider = 'custom';
        $providerConfig = [];
        foreach (app(ProviderRegistry::class)->all() as $candidate) {
            if (!$candidate->validUrl($url)) {
                continue;
            }
            $provider = $candidate->id();
            foreach ($candidate->fields() as $field => $definition) {
                if (($definition['type'] ?? '') === 'secret') {
                    $context->report(
                        'review',
                        ['plugin' => 'vote'] + $origin + ['reason' => 'provider_credentials_required'],
                    );

                    continue;
                }
                if (
                    $field === 'server_id' &&
                    is_scalar($providerData['server_id'] ?? null) &&
                    preg_match('/^[A-Za-z0-9_-]{1,200}$/D', (string) $providerData['server_id'])
                ) {
                    $providerConfig[$field] = (string) $providerData['server_id'];
                } elseif (isset($definition['default'])) {
                    $providerConfig[$field] = $definition['default'];
                }
            }
            break;
        }
        $timezone = $site['reset_timezone'];
        $timezone =
            is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
        $reset = $site['reset_time'];
        $reset =
            is_string($reset) && preg_match('/^((?:[01][0-9]|2[0-3]):[0-5][0-9])(?::[0-5][0-9])?$/D', $reset, $matches)
                ? $matches[1]
                : null;

        return [
            'target_table' => 'vote__sites',
            'target_id' => $context->insert('vote__sites', [
                'name' => mb_substr(strip_tags($site['name']), 0, 120),
                'url' => $url,
                'game' => $context->options['game'] ?? app(GameRegistry::class)->activeId(),
                'callback_secret' => Crypt::encryptString(Str::random(64)),
                'provider' => $provider,
                'provider_config' => $providerConfig === []
                        ? null
                        : Crypt::encryptString(json_encode($providerConfig, JSON_THROW_ON_ERROR)),
                'server_id' => $site['server_id'],
                'allow_guests' => (bool) $site['allow_guests'],
                'ip_mode' => $site['ip_mode'],
                'reset_time' => $reset,
                'reset_timezone' => $timezone,
                'cooldown_minutes' => max(1, (int) $site['cooldown_minutes']),
                'reward_points' => 1,
                'commands' => null,
                'enabled' => false,
                'created_at' => $site['created_at'],
                'updated_at' => now(),
            ]),
        ];
    }

    public function assertReplacementReady(Connection $target, array $options): void
    {
        if (($options['mode'] ?? 'merge') !== 'replace') {
            return;
        }
        $deliveries = $target
            ->table('vote__deliveries')
            ->where('status', 'processing')
            ->where('lease_expires_at', '>', now());
        $cache = Cache::store('database')->getStore();
        if (!($cache instanceof DatabaseStore)) {
            throw new LogicException('Vote replacement requires the database cache store.');
        }
        $locks = $cache->getLockConnection() ?? $cache->getConnection();
        $lockTable = config('cache.stores.database.lock_table') ?? 'cache_locks';
        $providers = null;
        if ($locks->getSchemaBuilder()->hasTable($lockTable)) {
            $prefix = $cache->getPrefix().VerificationLock::PREFIX;
            $pattern = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix).'%';
            $providers = $locks->table($lockTable)->where('expiration', '>', now()->timestamp);
            $providers->whereRaw($providers->getGrammar()->wrap('key')." LIKE ? ESCAPE '!'", [$pattern]);
        }
        $challenges = $target->table('vote__challenges')->whereNotNull('provider_claimed_at')->whereNull('used_at');
        foreach ([$deliveries, $providers, $challenges] as $query) {
            if (
                $query !== null &&
                ($query->getConnection()->transactionLevel() > 0 ? $query->lockForUpdate() : $query)->exists()
            ) {
                throw ValidationException::withMessages(['import' => __('vote::admin.imports.busy')]);
            }
        }
    }

    public function history(ImportContext $context, int|string $identity, array $vote, array $origin): array
    {
        $game = $context->options['game'] ?? app(GameRegistry::class)->activeId();
        $user = $vote['user_id'];
        $guest = null;
        $guestIdentity = null;
        if ($user === null) {
            $player = trim($vote['player']);
            if ($game === null || !preg_match('/^[A-Za-z0-9_ -]{1,60}$/D', $player)) {
                $context->report('archive', ['plugin' => 'vote'] + $origin + ['reason' => 'unusable_guest_identity']);

                return ['target_table' => null, 'target_id' => null];
            }
            $guestIdentity = hash_hmac('sha256', $game.':'.strtolower($player), config('app.key'));
            $context->target->table('vote__guests')->insertOrIgnore([
                'identity_key' => $guestIdentity,
                'game' => $game,
                'player_name' => $player,
                'last_seen_at' => $vote['guest_last_seen_at'],
            ]);
            $guest = $context->target->table('vote__guests')->where('identity_key', $guestIdentity)->value('id');
        }
        $siteId = $vote['site_id'];
        if ($siteId === null) {
            $key = $vote['site_key'];
            $siteId = $context->mapped('__vote_sites', $key);
            if ($siteId === null) {
                $siteId = $context->insert('vote__sites', [
                    'name' => 'Import #'.$vote['site_label'],
                    'url' => 'https://invalid.invalid',
                    'callback_secret' => Crypt::encryptString(Str::random(64)),
                    'game' => $game,
                    'enabled' => false,
                ]);
                $context->remember(
                    '__vote_sites',
                    ['id' => $key],
                    ['target_table' => 'vote__sites', 'target_id' => $siteId],
                );
            }
            $context->report(
                'review',
                ['plugin' => 'vote'] + $vote['site_origin'] + ['reason' => 'missing_historical_site'],
            );
        }
        $server = $context->target->table('vote__sites')->where('id', $siteId)->value('server_id');
        $id = $context->insert('vote__votes', [
            'site_id' => $siteId,
            'user_id' => $user,
            'guest_id' => $guest,
            'identity_key' => $guestIdentity,
            'server_id' => $server,
            'game' => $game,
            'provider_event' => 'import:'.$context->runId.':'.$identity,
            'points' => 1,
            'created_at' => $vote['created_at'],
        ]);
        $reward = $vote['reward_id'];
        if ($reward !== null && $user !== null) {
            $context->insert('vote__reward_claims', [
                'reward_id' => $reward,
                'user_id' => $user,
                'vote_id' => $id,
                'claim_key' => hash('sha256', 'import:'.$context->runId.':'.$identity),
                'period_key' => 'import:'.substr($context->runId, 0, 8),
                'snapshot' => json_encode(['historical' => true], JSON_THROW_ON_ERROR),
                'status' => $vote['collected'] ? 'processed' : 'cancelled',
                'created_at' => $vote['created_at'],
                'processed_at' => now(),
            ]);
        }

        return [
            'target_table' => 'vote__votes',
            'target_id' => $id,
            'metadata' => ['historical' => true, 'reward_id' => $reward],
        ];
    }
}
