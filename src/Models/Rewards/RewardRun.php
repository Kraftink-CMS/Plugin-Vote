<?php

namespace KraftInk\Plugins\Vote\Models\Rewards;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int $reward_id
 * @property int $cursor_user_id
 * @property string $period_key
 * @property string $status
 * @property array $snapshot
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RewardRun extends Model
{
    protected $table = 'vote__reward_runs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reward_id' => 'integer',
            'cursor_user_id' => 'integer',
            'snapshot' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class, 'reward_id');
    }
}
