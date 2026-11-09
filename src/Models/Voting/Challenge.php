<?php

namespace KraftInk\Plugins\Vote\Models\Voting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Models\Accounts\User;
use App\Models\Servers\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int $site_id
 * @property int|null $user_id
 * @property int|null $guest_id
 * @property int|null $server_id
 * @property int|null $vote_id
 * @property string $token
 * @property string|null $identity_key
 * @property string|null $browser_hash
 * @property string|null $ip_hash
 * @property string|null $ip_address
 * @property string|null $provider_token
 * @property string|null $game
 * @property string|null $player_uuid
 * @property string|null $player_name
 * @property bool $requires_online
 * @property string|null $commands
 * @property string $verification_state
 * @property Carbon|null $provider_claimed_at
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Guest|null $guest
 * @property-read Server|null $server
 */
class Challenge extends Model
{
    protected $table = 'vote__challenges';

    protected $guarded = ['id'];

    public const null UPDATED_AT = null;

    protected $hidden = ['identity_key', 'browser_hash', 'votifier_payload'];

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'user_id' => 'integer',
            'guest_id' => 'integer',
            'server_id' => 'integer',
            'vote_id' => 'integer',
            'requires_online' => 'boolean',
            'provider_claimed_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class, 'vote_id');
    }

    public function isOpen(): bool
    {
        return $this->used_at === null && now()->lt($this->expires_at);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
