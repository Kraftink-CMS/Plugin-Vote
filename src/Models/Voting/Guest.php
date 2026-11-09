<?php

namespace KraftInk\Plugins\Vote\Models\Voting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property string $identity_key
 * @property string $game
 * @property string $player_name
 * @property Carbon $last_seen_at
 * @property-read Collection<int, Vote> $votes
 */
class Guest extends Model
{
    protected $table = 'vote__guests';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $hidden = ['identity_key'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'guest_id');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class, 'guest_id');
    }
}
