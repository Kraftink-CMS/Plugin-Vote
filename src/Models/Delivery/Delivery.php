<?php

namespace KraftInk\Plugins\Vote\Models\Delivery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Models\Accounts\User;
use App\Models\Servers\Server;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use KraftInk\Plugins\Vote\Models\Rewards\RewardClaim;
use KraftInk\Plugins\Vote\Models\Voting\Guest;
use KraftInk\Plugins\Vote\Models\Voting\Vote;

/**
 * @mixin Builder<static>
 *
 * @property string $id
 * @property int $vote_id
 * @property int|null $user_id
 * @property int|null $guest_id
 * @property int|null $reward_claim_id
 * @property int|null $server_id
 * @property int|null $link_server_id
 * @property string|null $game
 * @property string|null $player_uuid
 * @property string|null $player_name
 * @property bool $requires_online
 * @property string $command
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $lease_token
 * @property Carbon|null $lease_expires_at
 * @property Carbon|null $available_at
 * @property Carbon|null $delivered_at
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Guest|null $guest
 * @property-read Server|null $server
 */
class Delivery extends Model
{
    use HasUuids;

    protected $table = 'vote__deliveries';

    protected $guarded = ['id'];

    public const null UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['lease_token', 'votifier_payload'];

    protected function casts(): array
    {
        return [
            'vote_id' => 'integer',
            'user_id' => 'integer',
            'guest_id' => 'integer',
            'reward_claim_id' => 'integer',
            'server_id' => 'integer',
            'link_server_id' => 'integer',
            'requires_online' => 'boolean',
            'attempts' => 'integer',
            'lease_expires_at' => 'datetime',
            'available_at' => 'datetime',
            'delivered_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class, 'vote_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function rewardClaim(): BelongsTo
    {
        return $this->belongsTo(RewardClaim::class, 'reward_claim_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function linkServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'link_server_id');
    }
}
