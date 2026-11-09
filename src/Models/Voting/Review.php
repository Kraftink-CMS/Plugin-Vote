<?php

namespace KraftInk\Plugins\Vote\Models\Voting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Models\Accounts\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int|null $challenge_id
 * @property int|null $user_id
 * @property int|null $guest_id
 * @property int|null $actor_id
 * @property int|null $vote_id
 * @property string $note
 * @property string $decision
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Guest|null $guest
 * @property-read User|null $actor
 */
class Review extends Model
{
    protected $table = 'vote__reviews';

    protected $guarded = ['id'];

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'challenge_id' => 'integer',
            'user_id' => 'integer',
            'guest_id' => 'integer',
            'actor_id' => 'integer',
            'vote_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class, 'vote_id');
    }
}
