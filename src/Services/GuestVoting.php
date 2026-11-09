<?php

namespace KraftInk\Plugins\Vote\Services;

use App\Kraft\Games\Contracts\ProvidesGuestIdentity;
use App\Kraft\Games\GameRegistry;
use App\Kraft\Link\CommandIdentity;
use App\Kraft\System\OperationLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Data\VoteContext;
use KraftInk\Plugins\Vote\Models\Delivery\Delivery;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;
use KraftInk\Plugins\Vote\Models\Voting\Guest;
use KraftInk\Plugins\Vote\Models\Voting\Site;
use KraftInk\Plugins\Vote\Models\Voting\SiteServer;
use KraftInk\Plugins\Vote\Models\Voting\Vote;
use KraftInk\Plugins\Vote\Services\Providers\ProviderRegistry;
use App\Kraft\Events\PluginEvents;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GuestVoting
{
    public function start(object $site, Request $request, string $player, ?int $server): array
    {
        $lease = app(OperationLock::class)->acquireShared();
        try {
            if (!$site->allow_guests) {
                throw new HttpException(403);
            }
            $games = app(GameRegistry::class);
            $game = $games->activeId();
            $adapter = $games->find($game);
            $uuid = null;
            if ($adapter instanceof ProvidesGuestIdentity) {
                try {
                    $identity = $adapter->guestIdentity($player);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'player' => array_merge(...array_values($exception->errors())),
                    ]);
                }
                $player = $identity['player'] ?? null;
                $uuid = ($identity['uuid'] ?? '') !== '' ? $identity['uuid'] : null;
            } elseif (!preg_match('/^[a-zA-Z0-9_]{1,32}$/D', $player)) {
                throw ValidationException::withMessages(['player' => __('vote::messages.guests.invalid_player')]);
            }
            if (
                !is_string($player) ||
                !preg_match('/^[a-zA-Z0-9_.:+-]{1,60}$/D', $player) ||
                ($uuid !== null && (!is_string($uuid) || !preg_match('/^[a-zA-Z0-9_.:+-]{1,64}$/D', $uuid)))
            ) {
                throw ValidationException::withMessages(['player' => __('vote::messages.guests.invalid_player')]);
            }
            $ip = $request->ip();
            $audience = app(Audience::class);
            $audience->assertIp($site, $ip);
            $keys = $audience->keys($game, $player, $ip);
            $audience->available($site, $keys);
            $choices = SiteServer::query()
                ->join('servers', 'servers.id', '=', 'vote__site_servers.server_id')
                ->where('site_id', $site->id)
                ->where('servers.game', $game)
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
            $commands = $votifier ? [] : json_decode($site->commands ?: '[]', true, 32, JSON_THROW_ON_ERROR);
            if (
                !$uuid &&
                ((!$votifier && $site->requires_online) || str_contains(implode('\n', $commands), '{uuid}'))
            ) {
                throw ValidationException::withMessages(['player' => __('vote::messages.guests.uuid_required')]);
            }
            $snapshot = app(CommandIdentity::class)->resolveGuest(
                $target,
                $commands,
                !$votifier && (bool) $site->requires_online,
                [
                    'player_name' => $player,
                    'player_uuid' => $uuid ?? '',
                ],
            );
            $votifierPayload = app(Votifier\VotifierDeliveries::class)->snapshot($site, $snapshot, $ip);
            Guest::query()->insertOrIgnore([
                'identity_key' => $keys['identity_key'],
                'game' => $game,
                'player_name' => $player,
                'last_seen_at' => now(),
            ]);
            $guest = Guest::query()->where('identity_key', $keys['identity_key'])->first();
            Guest::query()
                ->where('id', $guest->id)
                ->update(['last_seen_at' => now()]);
            $browser = $request->session()->get('vote.guest_browser');
            if (!is_string($browser) || strlen($browser) !== 64) {
                $browser = bin2hex(random_bytes(32));
                $request->session()->put('vote.guest_browser', $browser);
            }
            $browserHash = hash('sha256', $browser);
            $existing = Challenge::query()
                ->where('site_id', $site->id)
                ->where('guest_id', $guest->id)
                ->where('browser_hash', $browserHash)
                ->open()
                ->latest('id')
                ->first();
            if ($existing && $existing->provider_token) {
                if ($existing->server_id !== $target) {
                    throw new HttpException(422, __('vote::messages.verification.challenge_server_locked'));
                }

                return ['challenge' => $existing, 'url' => ChallengePayload::url($existing)];
            }
            $nonce = Str::random(64);
            $prepared =
                $site->provider === 'custom'
                    ? null
                    : app(ProviderRegistry::class)
                        ->get($site->provider)
                        ->prepare($site, new VoteContext(0, $player, $uuid, $ip, $nonce));
            $url = $prepared?->url ?? ChallengePayload::customUrl($site->url, $nonce, $player);
            $id = Challenge::query()->insertGetId(
                $keys +
                    $snapshot + [
                        'site_id' => $site->id,
                        'user_id' => null,
                        'guest_id' => $guest->id,
                        'browser_hash' => $browserHash,
                        'token' => hash('sha256', $nonce),
                        'expires_at' => now()->addSeconds($prepared?->expiresSeconds ?? 3600),
                        'created_at' => now(),
                        'commands' => $votifier ? '[]' : $site->commands,
                        'votifier_payload' => $votifierPayload,
                        'provider_token' => $prepared ? ChallengePayload::encode($prepared, $site->url) : null,
                        'ip_address' => Crypt::encryptString($ip),
                        'verification_state' => 'pending',
                    ],
            );

            return ['challenge' => Challenge::query()->find($id), 'url' => $url];
        } finally {
            $lease->release();
        }
    }

    public function owned(Request $request, int $id): object
    {
        $browser = $request->session()->get('vote.guest_browser');
        if (!(is_string($browser) && strlen($browser) === 64)) {
            abort(404);
        }
        $challenge = Challenge::query()
            ->whereNull('user_id')
            ->whereNotNull('guest_id')
            ->where('browser_hash', hash('sha256', $browser))
            ->find($id);
        if ($challenge === null) {
            abort(404);
        }

        return $challenge;
    }

    public function verify(object $site, object $challenge, Request $request): int
    {
        $lease = app(OperationLock::class)->acquireShared();
        try {
            $challenge = $this->owned($request, $challenge->id);
            if (!($site->allow_guests && $site->id === $challenge->site_id)) {
                throw new HttpException(403);
            }
            if ($challenge->verification_state === 'rejected') {
                throw new HttpException(422, __('vote::messages.verification.rejected'));
            }
            if ($challenge->used_at) {
                return (int) $challenge->vote_id;
            }
            if (!$challenge->isOpen()) {
                throw new HttpException(422, __('vote::messages.verification.expired'));
            }
            $audience = app(Audience::class);
            $audience->assertIp($site, $request->ip());
            $keys = $audience->keys($challenge->game, $challenge->player_name, $request->ip());
            if (!hash_equals($challenge->ip_hash, $keys['ip_hash'])) {
                throw new HttpException(422, __('vote::messages.guests.ip_changed'));
            }
            $audience->available($site, $keys);
            $provider = app(ProviderRegistry::class)->get($site->provider);
            $correlation =
                $provider instanceof Providers\ListingApi
                    ? $provider->correlation($challenge)
                    : strtolower($challenge->player_name);
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
            $lease->release();
        }

        return $voteId;
    }

    public function complete(object $site, object $original, string $event): int
    {
        return DB::transaction(function () use ($site, $original, $event) {
            $current = Site::query()->where('id', $site->id)->lockForUpdate()->first();
            if (!($current && $current->enabled && $current->allow_guests && $current->provider === $site->provider)) {
                throw new HttpException(422, __('vote::messages.verification.configuration_invalid'));
            }
            $site = $current;
            $guest = Guest::query()->where('id', $original->guest_id)->lockForUpdate()->first();
            if (!($guest && $original->user_id === null)) {
                throw new HttpException(422);
            }
            $duplicate = Vote::query()->where('site_id', $site->id)->where('provider_event', $event)->first();
            if ($duplicate) {
                if (!($duplicate->guest_id === $guest->id && $duplicate->user_id === null)) {
                    throw new HttpException(409, __('vote::messages.verification.already_claimed'));
                }

                return (int) $duplicate->id;
            }
            $challenge = Challenge::query()->where('id', $original->id)->lockForUpdate()->first();
            if (
                !(
                    $challenge &&
                    $challenge->guest_id === $guest->id &&
                    $challenge->verification_state !== 'rejected' &&
                    $challenge->isOpen()
                )
            ) {
                throw new HttpException(422, __('vote::messages.verification.expired'));
            }
            if ($challenge->game !== app(GameRegistry::class)->activeId()) {
                throw new HttpException(422, __('vote::messages.verification.game_changed'));
            }
            $keys = ['identity_key' => $challenge->identity_key, 'ip_hash' => $challenge->ip_hash];
            app(Audience::class)->available($site, $keys);
            $id = Vote::query()->insertGetId(
                $keys + [
                    'site_id' => $site->id,
                    'user_id' => null,
                    'guest_id' => $guest->id,
                    'provider_event' => $event,
                    'points' => $site->reward_points,
                    'game' => $challenge->game,
                    'server_id' => $challenge->server_id,
                    'created_at' => now(),
                ],
            );
            Challenge::query()
                ->where('id', $challenge->id)
                ->update(['used_at' => now(), 'verification_state' => 'confirmed', 'vote_id' => $id]);
            app(Votifier\VotifierDeliveries::class)->queue($id, $challenge);
            foreach (json_decode($challenge->commands ?: '[]', true, 32, JSON_THROW_ON_ERROR) as $command) {
                Delivery::query()->insert([
                    'id' => (string) Str::uuid(),
                    'vote_id' => $id,
                    'user_id' => null,
                    'guest_id' => $guest->id,
                    'command' => app(CommandIdentity::class)->expand($challenge, $command),
                    'server_id' => $challenge->server_id,
                    'game' => $challenge->game,
                    'player_name' => $challenge->player_name,
                    'player_uuid' => $challenge->player_uuid,
                    'requires_online' => $challenge->requires_online,
                    'status' => 'pending',
                    'attempts' => 0,
                    'available_at' => now(),
                    'created_at' => now(),
                ]);
            }

            app(PluginEvents::class)->publish(
                'vote.confirmed',
                [
                    'vote_id' => $id,
                    'site_id' => (int) $site->id,
                    'user_id' => null,
                    'guest_id' => (int) $guest->id,
                    'points' => (int) $site->reward_points,
                    'game' => $challenge->game,
                    'server_id' => $challenge->server_id,
                ],
                'vote.confirmed:'.$id,
            );

            return $id;
        }, 3);
    }

    public function forget(Request $request): void
    {
        $browser = $request->session()->pull('vote.guest_browser');
        if (is_string($browser)) {
            Challenge::query()
                ->where('browser_hash', hash('sha256', $browser))
                ->whereNull('provider_claimed_at')
                ->delete();
        }
    }
}
