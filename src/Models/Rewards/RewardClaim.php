<?php

namespace KraftInk\Plugins\Vote\Models\Rewards;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Models\Accounts\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use KraftInk\Plugins\Vote\Models\Voting\Vote;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int $reward_id
 * @property int $user_id
 * @property int|null $vote_id
 * @property string $claim_key
 * @property string $period_key
 * @property array $snapshot
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class RewardClaim extends Model
{
    protected $table = 'vote__reward_claims';

    protected $guarded = ['id'];

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'reward_id' => 'integer',
            'user_id' => 'integer',
            'vote_id' => 'integer',
            'snapshot' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class, 'reward_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class, 'vote_id');
    }
}
