<?php

namespace KraftInk\Plugins\Vote\Models\Voting;

use Illuminate\Support\Carbon;
use App\Kraft\Games\GameRegistry;
use App\Models\Accounts\User;
use App\Models\Servers\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KraftInk\Plugins\Vote\Models\Delivery\Delivery;
use KraftInk\Plugins\Vote\Models\Rewards\RewardClaim;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int $site_id
 * @property int|null $user_id
 * @property int|null $guest_id
 * @property int|null $server_id
 * @property string $provider_event
 * @property string|null $identity_key
 * @property string|null $ip_hash
 * @property string|null $game
 * @property int $points
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Guest|null $guest
 * @property-read Server|null $server
 */
class Vote extends Model
{
    protected $table = 'vote__votes';

    protected $guarded = ['id'];

    public const null UPDATED_AT = null;

    protected $hidden = ['identity_key'];

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'user_id' => 'integer',
            'guest_id' => 'integer',
            'server_id' => 'integer',
            'points' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'vote_id');
    }

    public function rewardClaims(): HasMany
    {
        return $this->hasMany(RewardClaim::class, 'vote_id');
    }

    public function scopeRewardEligible(Builder $query, object $reward, mixed $start, mixed $end): void
    {
        $query
            ->whereNotNull('user_id')
            ->whereNotLike('provider_event', 'import:%')
            ->where('game', $reward->game)
            ->where('created_at', '>=', $start)
            ->when($end, fn (Builder $query) => $query->where('created_at', '<', $end))
            ->when($reward->site_id, fn (Builder $query) => $query->where('site_id', $reward->site_id));
    }

    public function scopeScores(Builder $query): void
    {
        $game = app(GameRegistry::class)->activeId();
        $query
            ->join('users', 'users.id', '=', 'vote__votes.user_id')
            ->whereNull('users.anonymized_at')
            ->where('users.import_locked', false)
            ->where('users.banned_permanently', false)
            ->where(
                fn (Builder $query) => $query
                    ->whereNull('users.banned_until')
                    ->orWhere('users.banned_until', '<=', now()),
            )
            ->where(fn (Builder $query) => $query->where('vote__votes.game', $game)->orWhereNull('vote__votes.game'));
    }

    public function scopeRanking(Builder $query, bool $monthly): void
    {
        $query
            ->scores()
            ->select('users.id', 'users.username')
            ->selectRaw('SUM(vote__votes.points) AS points')
            ->when(
                $monthly,
                fn (Builder $query) => $query->where('vote__votes.created_at', '>=', now()->utc()->startOfMonth()),
            )
            ->groupBy('users.id', 'users.username')
            ->orderByDesc('points')
            ->orderBy('users.id');
    }
}
