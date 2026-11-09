<?php

namespace KraftInk\Plugins\Vote\Services\Votifier;

use App\Exceptions\TransportFailure;
use App\Kraft\Games\GameRegistry;
use App\Kraft\Link\DeliveryQueue;
use App\Kraft\System\OperationLock;
use App\Models\Servers\Server;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Models\Delivery\Delivery;
use KraftInk\Plugins\Vote\Models\Voting\Vote;
use Throwable;

class VotifierDeliveries
{
    public function capabilities(): array
    {
        $registry = app(GameRegistry::class);
        $game = $registry->activeId();

        $capabilities = config('vote.votifier.games.'.$game, []);
        if (!$capabilities || !($adapter = $registry->find($game))) {
            return [];
        }

        return $capabilities + ['username_rules' => $adapter->identityRules()['username']];
    }

    public function snapshot(object $site, array $identity, string $address): ?string
    {
        if (($site->delivery_mode ?? 'command') !== 'votifier') {
            return null;
        }
        $server = Server::query()->find($identity['server_id']);
        $name = $identity['player_name'] ?? '';
        $capabilities = $this->capabilities();
        if (
            !$capabilities ||
            !$server ||
            !$server->enabled ||
            $server->game !== app(GameRegistry::class)->activeId() ||
            !is_string($name) ||
            $name === '' ||
            strlen($name) > ($capabilities['username_max'] ?? 64) ||
            preg_match('/[\x00-\x1F\x7F]/', $name) ||
            Validator::make(
                ['username' => $name],
                ['username' => $capabilities['username_rules'] ?? ['required', 'string', 'max:64']],
            )->fails() ||
            !filter_var($address, FILTER_VALIDATE_IP) ||
            !$site->votifier_token
        ) {
            throw ValidationException::withMessages(['player' => __('vote::votifier.identity_required')]);
        }
        $vote = [
            'serviceName' => $site->votifier_website_name,
            'username' => $name,
            'address' => $address,
        ];
        $uuid = str_replace('-', '', $identity['player_uuid'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/iD', $uuid)) {
            $vote['uuid'] =
                substr($uuid, 0, 8).
                '-'.
                substr($uuid, 8, 4).
                '-'.
                substr($uuid, 12, 4).
                '-'.
                substr($uuid, 16, 4).
                '-'.
                substr($uuid, 20);
        }

        return Crypt::encryptString(
            json_encode(
                [
                    'host' => $server->host,
                    'port' => (int) $site->votifier_port,
                    'protocol' => 'v2',
                    'token' => Crypt::decryptString($site->votifier_token),
                    'vote' => $vote,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    public function queue(int $voteId, object $challenge): void
    {
        if (!$challenge->votifier_payload) {
            return;
        }
        $vote = Vote::query()->findOrFail($voteId);
        if (str_starts_with($vote->provider_event, 'import:')) {
            return;
        }
        Delivery::query()->insert([
            'id' => (string) Str::uuid(),
            'vote_id' => $voteId,
            'user_id' => $challenge->user_id,
            'guest_id' => $challenge->guest_id,
            'server_id' => $challenge->server_id,
            'game' => $challenge->game,
            'player_name' => $challenge->player_name,
            'player_uuid' => $challenge->player_uuid,
            'channel' => 'votifier',
            'votifier_payload' => $challenge->votifier_payload,
            'command' => 'Votifier v2',
            'requires_online' => false,
            'status' => 'pending',
            'available_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function retryPayload(Delivery $delivery): string
    {
        $configuration = json_decode(Crypt::decryptString($delivery->votifier_payload), true, 16, JSON_THROW_ON_ERROR);
        $site = $delivery->vote?->site;
        if (!$site || $site->delivery_mode !== 'votifier' || $site->server_id !== $delivery->server_id) {
            throw ValidationException::withMessages(['delivery' => __('vote::votifier.retry_configuration')]);
        }

        return $this->snapshot(
            $site,
            [
                'server_id' => $delivery->server_id,
                'player_name' => $delivery->player_name,
                'player_uuid' => $delivery->player_uuid,
            ],
            $configuration['vote']['address'],
        );
    }

    public function run(int $limit = 200): int
    {
        return app(OperationLock::class)->shared(fn () => $this->pending($limit));
    }

    private function pending(int $limit): int
    {
        if (DB::transactionLevel() > 0 || app()->isDownForMaintenance()) {
            return 0;
        }
        $deadline = $this->monotonicTime() + 20;
        Delivery::query()
            ->where('channel', 'votifier')
            ->where('status', 'processing')
            ->where('lease_expires_at', '<=', now())
            ->update(['status' => 'failed', 'last_error' => 'transports.uncertain']);
        $ids = Delivery::query()
            ->where('channel', 'votifier')
            ->where('game', app(GameRegistry::class)->activeId())
            ->where('status', 'pending')
            ->where('attempts', '<', DeliveryQueue::MAX_ATTEMPTS)
            ->where('available_at', '<=', now())
            ->orderBy('created_at')
            ->limit(max(1, min(50, $limit)))
            ->pluck('id');
        $processed = 0;
        foreach ($ids as $id) {
            if ($this->monotonicTime() >= $deadline) {
                break;
            }
            $this->deliver($id);
            $processed++;
        }

        return $processed;
    }

    protected function monotonicTime(): float
    {
        return hrtime(true) / 1000000000;
    }

    public function deliver(string $id): void
    {
        app(OperationLock::class)->shared(function () use ($id): void {
            if (DB::transactionLevel() > 0 || app()->isDownForMaintenance()) {
                return;
            }
            $delivery = DB::transaction(function () use ($id): ?Delivery {
                $record = Delivery::query()
                    ->whereKey($id)
                    ->where('channel', 'votifier')
                    ->where('game', app(GameRegistry::class)->activeId())
                    ->lockForUpdate()
                    ->first();
                if (
                    !$record ||
                    $record->status !== 'pending' ||
                    $record->attempts >= DeliveryQueue::MAX_ATTEMPTS ||
                    now()->lt($record->available_at)
                ) {
                    return null;
                }
                $record->update([
                    'status' => 'processing',
                    'attempts' => $record->attempts + 1,
                    'lease_token' => Str::random(64),
                    'lease_expires_at' => now()->addMinute(),
                ]);

                return $record;
            }, 3);
            if (!$delivery) {
                return;
            }
            $writing = false;
            try {
                $server = $delivery->server;
                $user = $delivery->user;
                if (
                    !$server ||
                    !$server->enabled ||
                    $server->game !== $delivery->game ||
                    ($delivery->user_id &&
                        (!$user || $user->anonymized_at || $user->import_locked || $user->isBanned()))
                ) {
                    $this->finish($delivery, ['status' => 'cancelled', 'votifier_payload' => null]);

                    return;
                }
                $configuration = json_decode(
                    Crypt::decryptString($delivery->votifier_payload),
                    true,
                    16,
                    JSON_THROW_ON_ERROR,
                );
                $vote = $configuration['vote'] + ['timestamp' => $delivery->created_at->getTimestampMs()];
                app(VotifierClient::class)->send($configuration, $vote, function () use (
                    &$writing,
                ): void {
                    $writing = true;
                });
                $values = [
                    'status' => 'delivered',
                    'delivered_at' => now(),
                    'last_error' => null,
                    'votifier_payload' => null,
                ];
            } catch (Throwable $exception) {
                $terminal = !($exception instanceof TransportFailure) || $exception->terminal;
                $attemptsExhausted = $delivery->attempts >= DeliveryQueue::MAX_ATTEMPTS;
                $values = [
                    'status' => $writing || $terminal || $attemptsExhausted ? 'failed' : 'pending',
                    'last_error' => $writing
                        ? 'transports.uncertain'
                        : ($exception instanceof TransportFailure
                            ? $exception->reason
                            : 'transports.unavailable'),
                    'available_at' => now()->addMinutes(min(60, 2 ** min($delivery->attempts, 6))),
                ];
            }
            $this->finish($delivery, $values);
        });
    }

    private function finish(Delivery $delivery, array $values): void
    {
        Delivery::query()
            ->whereKey($delivery->id)
            ->where('status', 'processing')
            ->where('lease_token', $delivery->lease_token)
            ->update($values + ['lease_token' => null, 'lease_expires_at' => null]);
    }
}
