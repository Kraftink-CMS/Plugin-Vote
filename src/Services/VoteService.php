<?php

namespace KraftInk\Plugins\Vote\Services;

use App\Kraft\Games\GameRegistry;
use App\Kraft\System\OperationLock;
use App\Models\Accounts\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Data\VoteContext;
use KraftInk\Plugins\Vote\Models\Delivery\Delivery;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;
use KraftInk\Plugins\Vote\Models\Voting\Site;
use KraftInk\Plugins\Vote\Models\Voting\SiteServer;
use KraftInk\Plugins\Vote\Models\Voting\Vote;
use App\Kraft\Link\CommandIdentity;
use KraftInk\Plugins\Vote\Services\Providers\ProviderRegistry;
use KraftInk\Plugins\Vote\Services\Rewards\RewardEngine;
use App\Kraft\Events\PluginEvents;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VoteService
{
    public function available(object $site, User $user): void
    {
        if ($user->isBanned() || $user->anonymized_at || $user->import_locked) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.verification.account_unavailable')]);
        }
        $latest = Vote::query()
            ->where('site_id', $site->id)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('created_at');
        $next = app(Cooldown::class)->next($site, $latest);
        if ($next?->isFuture()) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.site.cooldown')]);
        }
    }

    public function start(object $site, User $user, string $ip, ?int $server = null): array
    {
        $operationLease = app(OperationLock::class)->acquireShared();
        try {
            $this->available($site, $user);
            $choices = SiteServer::query()
                ->join('servers', 'servers.id', '=', 'vote__site_servers.server_id')
                ->where('site_id', $site->id)
                ->where('servers.game', app(GameRegistry::class)->activeId())
                ->pluck('servers.id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if (($choices && !in_array($server, $choices, true)) || (!$choices && $server !== null)) {
                throw ValidationException::withMessages([
                    'server_id' => __('vote::messages.verification.select_server'),
                ]);
            }
            $target = $choices ? $server : ($site->server_id ? (int) $site->server_id : null);
            $votifier = ($site->delivery_mode ?? 'command') === 'votifier';
            $snapshot = app(CommandIdentity::class)->resolve(
                $user,
                $target,
                $votifier ? ['{player}'] : json_decode($site->commands ?: '[]', true),
                !$votifier && (bool) $site->requires_online,
            );
            $snapshot['player_name'] ??= $user->username;
            app(Audience::class)->assertIp($site, $ip);
            $game = app(GameRegistry::class)->activeId();
            $audience = app(Audience::class)->keys(
                $game,
                app(Audience::class)->player($game, $snapshot['player_name'], $snapshot['player_uuid']),
                $ip,
            );
            app(Audience::class)->available($site, $audience);
            $votifierPayload = app(Votifier\VotifierDeliveries::class)->snapshot($site, $snapshot, $ip);
            $token = Str::random(64);
            $provider = $site->provider !== 'custom' ? app(ProviderRegistry::class)->get($site->provider) : null;
            $existing = Challenge::query()
                ->where('site_id', $site->id)
                ->where('user_id', $user->id)
                ->where('game', $game)
                ->open()
                ->latest('id')
                ->first();
            if ($provider && $existing) {
                if ($existing->server_id !== $target) {
                    throw ValidationException::withMessages([
                        'server_id' => __('vote::messages.verification.challenge_server_locked'),
                    ]);
                }

                return ['challenge' => $existing, 'url' => $this->url($site, $existing)];
            }
            $prepared = $provider?->prepare(
                $site,
                new VoteContext(
                    $user->id,
                    $snapshot['player_name'],
                    $snapshot['player_uuid'],
                    $ip,
                    $token,
                ),
            );
            $values = [
                'site_id' => $site->id,
                'user_id' => $user->id,
                'token' => hash('sha256', $token),
                'expires_at' => now()->addSeconds($prepared?->expiresSeconds ?? 3600),
                'commands' => $votifier ? '[]' : $site->commands,
                'votifier_payload' => $votifierPayload,
                'created_at' => now(),
                'provider_token' => $prepared ? ChallengePayload::encode($prepared, $site->url) : null,
                'ip_address' => Crypt::encryptString($ip),
                'verification_state' => 'pending',
            ];
            $id = Challenge::query()->insertGetId($values + $snapshot + $audience);
            $challenge = Challenge::query()->find($id);

            return [
                'challenge' => $challenge,
                'url' => $prepared?->url ?? ChallengePayload::customUrl($site->url, $token, $snapshot['player_name']),
            ];
        } finally {
            $operationLease->release();
        }
    }

    public function verify(object $site, object $challenge, User $user): int
    {
        $operationLease = app(OperationLock::class)->acquireShared();
        try {
            if (!($challenge->user_id === $user->id && $challenge->site_id === $site->id)) {
                abort(404);
            }
            if ($challenge->verification_state === 'rejected') {
                throw new HttpException(422, __('vote::messages.verification.rejected'));
            }
            if ($challenge->used_at) {
                return (int) $challenge->vote_id;
            }
            if (!now()->lt($challenge->expires_at)) {
                throw new HttpException(422, __('vote::messages.verification.expired'));
            }
            $this->available($site, $user);
            $provider = app(ProviderRegistry::class)->get($site->provider);
            $correlation =
                $provider instanceof Providers\ListingApi
                    ? $provider->correlation($challenge)
                    : strtolower($challenge->player_name ?? (string) $user->id);
            $identity = hash('sha256', $site->id.':'.$site->provider.':'.$correlation);
            $lock = app(VerificationLock::class)->acquire($identity);
            try {
                $challenge = Challenge::query()->find($challenge->id);
                if ($challenge === null) {
                    abort(404);
                }
                if ($challenge->verification_state === 'rejected') {
                    throw new HttpException(422, __('vote::messages.verification.rejected'));
                }
                if ($challenge->used_at) {
                    $voteId = (int) $challenge->vote_id;
                } elseif (!$challenge->isOpen()) {
                    throw new HttpException(422, __('vote::messages.verification.expired'));
                } elseif ($challenge->game !== app(GameRegistry::class)->activeId()) {
                    throw new HttpException(422, __('vote::messages.verification.game_changed'));
                } else {
                    $proof = app(ProviderRegistry::class)->get($site->provider)->verify($site, $challenge);
                    $voteId = $this->complete($site, $challenge, $proof->eventId);
                }
            } finally {
                $lock->release();
            }
        } finally {
            $operationLease->release();
        }

        return $voteId;
    }

    public function complete(object $site, object $challenge, string $event): int
    {
        $operationLease = app(OperationLock::class)->acquireShared();
        try {
            if ($challenge->guest_id ?? null) {
                return app(GuestVoting::class)->complete($site, $challenge, $event);
            }
            $vote = DB::transaction(function () use ($site, $challenge, $event) {
                $current = Site::query()->where('id', $site->id)->lockForUpdate()->first();
                if (!($current && $current->enabled && $current->provider === $site->provider)) {
                    throw new HttpException(422, __('vote::messages.verification.configuration_invalid'));
                }
                $site = $current;
                $user = User::query()->whereKey($challenge->user_id)->lockForUpdate()->firstOrFail();
                $duplicate = Vote::query()->where('site_id', $site->id)->where('provider_event', $event)->first();
                if ($duplicate) {
                    if ($duplicate->user_id !== $challenge->user_id) {
                        throw new HttpException(409, __('vote::messages.verification.already_claimed'));
                    }

                    return $duplicate;
                }
                $challenge = Challenge::query()->where('id', $challenge->id)->lockForUpdate()->first();
                if (!$challenge?->isOpen()) {
                    throw new HttpException(422, __('vote::messages.verification.expired'));
                }
                if ($challenge->user_id !== $user->id) {
                    throw new HttpException(409);
                }
                $this->available($site, $user);
                $game = app(GameRegistry::class)->activeId();
                if ($challenge->game !== $game) {
                    throw new HttpException(422, __('vote::messages.verification.game_changed'));
                }
                $snapshot = [
                    'server_id' => $challenge->server_id,
                    'game' => $challenge->game,
                    'player_uuid' => $challenge->player_uuid,
                    'player_name' => $challenge->player_name,
                    'command_identity' => $challenge->command_identity,
                    'requires_online' => $challenge->requires_online,
                ];
                $audience = $challenge->ip_address
                    ? app(Audience::class)->keys(
                        $game,
                        app(Audience::class)->player(
                            $game,
                            $snapshot['player_name'] ?? $user->username,
                            $snapshot['player_uuid'],
                        ),
                        Crypt::decryptString($challenge->ip_address),
                    )
                    : ['identity_key' => null, 'ip_hash' => null];
                if ($audience['ip_hash']) {
                    app(Audience::class)->available($site, $audience);
                }
                $id = Vote::query()->insertGetId(
                    [
                        'site_id' => $site->id,
                        'user_id' => $user->id,
                        'provider_event' => $event,
                        'points' => $site->reward_points,
                        'game' => $game,
                        'server_id' => $snapshot['server_id'],
                        'created_at' => now(),
                    ] + $audience,
                );
                Challenge::query()
                    ->where('id', $challenge->id)
                    ->update(['used_at' => now(), 'verification_state' => 'confirmed', 'vote_id' => $id]);
                app(Votifier\VotifierDeliveries::class)->queue($id, $challenge);
                foreach (json_decode($challenge->commands ?: '[]', true, 32, JSON_THROW_ON_ERROR) as $command) {
                    Delivery::query()->insert(
                        [
                            'id' => (string) Str::uuid(),
                            'vote_id' => $id,
                            'user_id' => $user->id,
                            'command' => app(CommandIdentity::class)->expand((object) $snapshot, $command),
                            'status' => 'pending',
                            'attempts' => 0,
                            'available_at' => now(),
                            'created_at' => now(),
                        ] + Arr::except($snapshot, 'command_identity'),
                    );
                }
                $vote = Vote::query()->find($id);
                app(RewardEngine::class)->voted($vote, $user, !$challenge->votifier_payload);
                app(PluginEvents::class)->publish(
                    'vote.confirmed',
                    [
                        'vote_id' => $id,
                        'site_id' => (int) $site->id,
                        'user_id' => (int) $user->id,
                        'guest_id' => null,
                        'points' => (int) $site->reward_points,
                        'game' => $game,
                        'server_id' => $snapshot['server_id'],
                    ],
                    'vote.confirmed:'.$id,
                );

                return $vote;
            }, 3);
            foreach ($vote->rewardClaims()->where('status', 'pending')->pluck('id') as $id) {
                app(RewardEngine::class)->process((int) $id);
            }

            return (int) $vote->id;
        } finally {
            $operationLease->release();
        }
    }

    public function resume(object $site, object $challenge): array
    {
        $operationLease = app(OperationLock::class)->acquireShared();
        try {
            return ['challenge' => $challenge, 'url' => $this->url($site, $challenge)];
        } finally {
            $operationLease->release();
        }
    }

    private function url(object $site, object $challenge): string
    {
        return $challenge->provider_token ? ChallengePayload::url($challenge) : $site->url;
    }
}
