<?php

namespace KraftInk\Plugins\Vote\Models\Voting;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Servers\Server;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin Builder<static>
 *
 * @property int $site_id
 * @property int $server_id
 * @property-read Server|null $server
 */
class SiteServer extends Pivot
{
    protected $table = 'vote__site_servers';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'server_id' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
