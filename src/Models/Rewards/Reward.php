<?php

namespace KraftInk\Plugins\Vote\Models\Rewards;

use Illuminate\Support\Carbon;
use App\Kraft\Games\GameRegistry;
use App\Kraft\LocalePolicy;
use App\Kraft\TextTranslations;
use App\Models\Accounts\Role;
use App\Models\Servers\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KraftInk\Plugins\Vote\Models\Voting\Site;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int|null $site_id
 * @property int|null $role_id
 * @property int|null $server_id
 * @property array $titles
 * @property string $type
 * @property string|null $game
 * @property string $period
 * @property string $metric
 * @property int $threshold
 * @property int|null $rank_to
 * @property string|null $pool
 * @property int $weight
 * @property int $chance_bps
 * @property string|null $permission
 * @property array|null $role_permissions
 * @property int|null $role_days
 * @property int $wallet_minor
 * @property string|null $wallet_currency
 * @property string|null $commands
 * @property bool $requires_online
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server|null $server
 */
class Reward extends Model
{
    protected $table = 'vote__rewards';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'role_id' => 'integer',
            'server_id' => 'integer',
            'titles' => 'array',
            'threshold' => 'integer',
            'rank_to' => 'integer',
            'weight' => 'integer',
            'chance_bps' => 'integer',
            'role_permissions' => 'array',
            'role_days' => 'integer',
            'wallet_minor' => 'integer',
            'requires_online' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(RewardClaim::class, 'reward_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(RewardRun::class, 'reward_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('enabled', true)->where('game', app(GameRegistry::class)->activeId());
    }

    public static function localizedTitle(array $titles, ?string $locale = null): string
    {
        $primaryLocale = app(LocalePolicy::class)->defaultLocale();
        $base =
            $titles[$primaryLocale] ??
            (collect($titles)->first(fn ($title) => is_string($title) && trim($title) !== '') ?? '');

        return app(TextTranslations::class)->resolve(
            $base,
            array_map(fn ($title) => ['title' => $title], $titles),
            'title',
            $primaryLocale,
            $locale,
        );
    }
}
