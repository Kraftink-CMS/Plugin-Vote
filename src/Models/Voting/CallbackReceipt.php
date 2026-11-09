<?php

namespace KraftInk\Plugins\Vote\Models\Voting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int $site_id
 * @property int $challenge_id
 * @property string $provider_event
 * @property Carbon $created_at
 */
class CallbackReceipt extends Model
{
    protected $table = 'vote__callback_receipts';

    protected $guarded = ['id'];

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'challenge_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }
}
