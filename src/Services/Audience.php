<?php

namespace KraftInk\Plugins\Vote\Services;

use App\Kraft\Games\Contracts\ProvidesCommandIdentity;
use App\Kraft\Games\GameRegistry;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Models\Voting\Vote;

class Audience
{
    public function player(string $game, string $name, ?string $uuid): string
    {
        $adapter = app(GameRegistry::class)->find($game);
        if ($uuid !== null && $uuid !== '' && $adapter instanceof ProvidesCommandIdentity) {
            $identity = $adapter->commandIdentity($uuid, $name);

            return $identity['player'];
        }

        return $name;
    }

    public function keys(string $game, string $player, string $ip): array
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.guests.invalid_ip')]);
        }

        return [
            'identity_key' => hash_hmac('sha256', $game.':'.strtolower($player), config('app.key')),
            'ip_hash' => hash_hmac('sha256', $packed, config('app.key')),
        ];
    }

    public function assertIp(object $site, string $ip): void
    {
        $flag = match ($site->ip_mode ?? 'any') {
            'ipv4' => FILTER_FLAG_IPV4,
            'ipv6' => FILTER_FLAG_IPV6,
            default => 0,
        };
        if (!filter_var($ip, FILTER_VALIDATE_IP, $flag)) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.guests.invalid_ip')]);
        }
    }

    public function available(object $site, array $keys): void
    {
        if (!$site->allow_guests) {
            return;
        }
        $latest = Vote::query()
            ->where('site_id', $site->id)
            ->where(
                fn ($query) => $query
                    ->where('identity_key', $keys['identity_key'])
                    ->orWhere('ip_hash', $keys['ip_hash']),
            )
            ->max('created_at');
        if (app(Cooldown::class)->next($site, $latest)?->isFuture()) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.site.cooldown')]);
        }
    }
}
