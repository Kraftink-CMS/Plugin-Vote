<?php

namespace KraftInk\Plugins\Vote\Models\Rewards;

use Illuminate\Support\Carbon;
use App\Kraft\Games\GameRegistry;
use App\Models\Accounts\Role;
use App\Models\Accounts\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property int $claim_id
 * @property array $permissions
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class RoleGrant extends Model
{
    protected $table = 'vote__role_grants';

    protected $guarded = ['id'];

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'role_id' => 'integer',
            'claim_id' => 'integer',
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(RewardClaim::class, 'claim_id');
    }

    public function scopeActive(Builder $query): void
    {
        $game = app(GameRegistry::class)->activeId();
        $query
            ->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas(
                'claim',
                fn (Builder $claim) => $claim
                    ->where('status', 'processed')
                    ->where(
                        fn (Builder $snapshot) => $snapshot
                            ->whereNull('snapshot->game')
                            ->orWhere('snapshot->game', $game),
                    ),
            );
    }
}
