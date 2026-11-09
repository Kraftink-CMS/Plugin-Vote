<?php

namespace KraftInk\Plugins\Vote\Listeners;

use App\Events\PruningData;
use Illuminate\Support\Facades\DB;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;

class DataRetention
{
    public function prune(PruningData $event): void
    {
        $expired = Challenge::query()->where(
            fn ($query) => $query
                ->where('expires_at', '<', now()->subDays(30))
                ->orWhere(
                    fn ($query) => $query->where('expires_at', '<', now()->subDays(2))->whereNull('provider_claimed_at'),
                ),
        );
        if ($event->dryRun) {
            $event->add('vote.challenges', $expired->count());

            return;
        }
        $count = DB::transaction(function () use ($expired): int {
            $ids = (clone $expired)->orderBy('id')->limit(500)->lockForUpdate()->pluck('id');

            return $expired->whereIn('id', $ids)->delete();
        }, 3);
        $event->add('vote.challenges', $count);
    }
}
