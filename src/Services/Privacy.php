<?php

namespace KraftInk\Plugins\Vote\Services;

use App\Events\AccountDeleting;
use App\Events\ExportingPersonalData;
use App\Kraft\Extensions\Contracts\PrivacyHandler;
use KraftInk\Plugins\Vote\Models\Delivery\Delivery;
use KraftInk\Plugins\Vote\Models\Rewards\RewardClaim;
use KraftInk\Plugins\Vote\Models\Rewards\RoleGrant;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;
use KraftInk\Plugins\Vote\Models\Voting\Review;
use KraftInk\Plugins\Vote\Models\Voting\Vote;

class Privacy implements PrivacyHandler
{
    public function export(ExportingPersonalData $event): void
    {
        $event->data['vote'] = [
            'votes' => Vote::query()
                ->join('vote__sites', 'vote__sites.id', '=', 'vote__votes.site_id')
                ->where('user_id', $event->user->id)
                ->orderBy('vote__votes.id')
                ->select(['vote__votes.id', 'site_id', 'vote__sites.name', 'points', 'vote__votes.created_at'])
                ->cursor(),
            'deliveries' => Delivery::query()
                ->where('user_id', $event->user->id)
                ->orderBy('created_at')
                ->select([
                    'id',
                    'vote_id',
                    'status',
                    'attempts',
                    'game',
                    'player_name',
                    'player_uuid',
                    'created_at',
                    'delivered_at',
                ])
                ->cursor(),
            'rewards' => RewardClaim::query()
                ->where('user_id', $event->user->id)
                ->orderBy('id')
                ->select(['id', 'reward_id', 'vote_id', 'period_key', 'status', 'error', 'created_at', 'processed_at'])
                ->cursor(),
            'role_grants' => RoleGrant::query()
                ->where('user_id', $event->user->id)
                ->orderBy('id')
                ->select(['id', 'role_id', 'claim_id', 'expires_at', 'revoked_at', 'created_at'])
                ->cursor(),
            'reviews' => Review::query()
                ->where('user_id', $event->user->id)
                ->orderBy('id')
                ->select(['id', 'vote_id', 'decision', 'created_at'])
                ->cursor(),
        ];
    }

    public function anonymize(AccountDeleting $event): void
    {
        if (
            Delivery::query()
                ->where('user_id', $event->user->id)
                ->where('status', 'processing')
                ->where('lease_expires_at', '>', now())
                ->exists()
        ) {
            $event->blockedReason = __('vote::admin.sites.deletion_delivery_pending');

            return;
        }
        Challenge::query()->where('user_id', $event->user->id)->delete();
        RewardClaim::query()
            ->where('user_id', $event->user->id)
            ->whereIn('status', ['pending', 'error'])
            ->update(['status' => 'cancelled', 'error' => null, 'processed_at' => now()]);
        RoleGrant::query()
            ->where('user_id', $event->user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        Review::query()
            ->where('user_id', $event->user->id)
            ->update(['note' => $event->replacementName]);
        Review::query()
            ->where('actor_id', $event->user->id)
            ->update(['actor_id' => null]);
        Delivery::query()->where('user_id', $event->user->id)->delete();
        Vote::query()->where('user_id', $event->user->id)->delete();
    }
}
