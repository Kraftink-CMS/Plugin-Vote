<?php

namespace KraftInk\Plugins\Vote\Models\Voting;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use App\Kraft\Games\GameRegistry;
use App\Models\Servers\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KraftInk\Plugins\Vote\Models\Rewards\Reward;
use KraftInk\Plugins\Vote\Services\Providers\ProviderRegistry;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int|null $server_id
 * @property string $name
 * @property string|null $game
 * @property string $url
 * @property string $provider
 * @property string|null $provider_config
 * @property string $callback_secret
 * @property int $cooldown_minutes
 * @property string|null $reset_time
 * @property string $reset_timezone
 * @property int $reward_points
 * @property string|null $commands
 * @property bool $requires_online
 * @property bool $allow_guests
 * @property string $ip_mode
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server|null $server
 * @property-read Collection<int, Vote> $votes
 * @property-read Collection<int, Server> $servers
 */
class Site extends Model
{
    protected $table = 'vote__sites';

    protected $guarded = ['id'];

    protected $hidden = ['callback_secret', 'provider_config', 'votifier_token'];

    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
            'votifier_port' => 'integer',
            'cooldown_minutes' => 'integer',
            'reward_points' => 'integer',
            'requires_online' => 'boolean',
            'allow_guests' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'site_id');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class, 'site_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class, 'site_id');
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'vote__site_servers', 'site_id', 'server_id')->using(
            SiteServer::class,
        );
    }

    public function scopeActiveGame(Builder $query): void
    {
        $game = app(GameRegistry::class)->activeId();
        $query
            ->where(fn (Builder $query) => $query->where('game', $game)->orWhereNull('game'))
            ->where(
                fn (Builder $query) => $query
                    ->whereNull('server_id')
                    ->orWhereIn('server_id', Server::query()->where('game', $game)->select('id')),
            );
    }

    public function scopeAvailable(Builder $query): void
    {
        $game = app(GameRegistry::class)->activeId();
        $providers = ['custom'];
        foreach (app(ProviderRegistry::class)->all() as $provider) {
            $games = $provider->games();
            if ($games === [] || in_array($game, $games, true)) {
                $providers[] = $provider->id();
            }
        }
        $query
            ->activeGame()
            ->where('enabled', true)
            ->whereIn('provider', $providers);
    }
}
